<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\WebChat;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

final class WebChatController extends Controller
{
    public function __construct(private readonly WebChatBridge $bridge) {}

    // ─────────────────────────────────────────────────────────────────────────
    // POST /telegram-support/chat/start
    // ─────────────────────────────────────────────────────────────────────────

    public function start(Request $request): JsonResponse
    {
        $ip = $request->ip();

        if ($this->tooManyAttempts("webchat-start:{$ip}", 10)) {
            return response()->json(['error' => 'Too many requests.'], 429);
        }

        // Resume existing session
        $token = $request->input('session_token');

        if ($token) {
            $session = WebChatSession::where('session_token', $token)
                ->whereIn('status', ['open', 'in_progress'])
                ->first();

            if ($session) {
                return response()->json([
                    'session_token' => $session->session_token,
                    'resumed' => true,
                    'status' => $session->status,
                ]);
            }
        }

        // Create a new session
        $session = WebChatSession::create([
            'display_name' => $this->sanitize($request->input('name')),
            'email' => $this->sanitize($request->input('email')),
            'status' => 'open',
        ]);

        return response()->json([
            'session_token' => $session->session_token,
            'resumed' => false,
            'status' => 'open',
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /telegram-support/chat/send
    // ─────────────────────────────────────────────────────────────────────────

    public function send(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json(['error' => 'Session invalide ou expirée.'], 401);
        }

        if ($session->isClosed()) {
            return response()->json(['error' => 'Cette session est fermée.'], 422);
        }

        if ($this->tooManyAttempts("webchat-send:{$session->session_token}", 30)) {
            return response()->json(['error' => 'Trop de messages. Attendez un moment.'], 429);
        }

        $text = mb_trim((string) $request->input('message', ''));

        if ($text === '') {
            return response()->json(['error' => 'Le message ne peut pas être vide.'], 422);
        }

        if (mb_strlen($text) > 4000) {
            return response()->json(['error' => 'Message trop long (max 4000 caractères).'], 422);
        }

        $groupMsgId = $this->bridge->forwardTextToGroup($session, $text);

        $msg = WebChatMessage::create([
            'session_id' => $session->id,
            'direction' => 'user_to_agent',
            'content' => $text,
            'group_message_id' => $groupMsgId,
        ]);

        $session->update(['last_activity_at' => now()]);

        return response()->json(['id' => $msg->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /telegram-support/chat/upload
    // ─────────────────────────────────────────────────────────────────────────

    public function upload(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json(['error' => 'Session invalide ou expirée.'], 401);
        }

        if ($session->isClosed()) {
            return response()->json(['error' => 'Cette session est fermée.'], 422);
        }

        if ($this->tooManyAttempts("webchat-upload:{$session->session_token}", 10)) {
            return response()->json(['error' => 'Trop de fichiers envoyés.'], 429);
        }

        $maxMb = (int) config('telegramlogs.web_chat.max_upload_mb', 20);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:'.($maxMb * 1024),
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first('file')], 422);
        }

        $file = $request->file('file');
        $result = $this->bridge->forwardFileToGroup($session, $file);

        $msg = WebChatMessage::create([
            'session_id' => $session->id,
            'direction' => 'user_to_agent',
            'content' => null,
            'media_path' => $result['media_path'],
            'media_name' => $result['media_name'],
            'media_mime' => $result['media_mime'],
            'media_size' => $result['media_size'],
            'group_message_id' => $result['group_message_id'],
        ]);

        // Also track the header message if different (so agents can reply to it)
        if (
            isset($result['header_message_id']) &&
            $result['header_message_id'] !== $result['group_message_id']
        ) {
            WebChatMessage::create([
                'session_id' => $session->id,
                'direction' => 'user_to_agent',
                'content' => null,
                'group_message_id' => $result['header_message_id'],
            ]);
        }

        $session->update(['last_activity_at' => now()]);

        return response()->json([
            'id' => $msg->id,
            'name' => $result['media_name'],
            'size' => $result['media_size'],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /telegram-support/chat/messages?after=0
    // ─────────────────────────────────────────────────────────────────────────

    public function messages(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json(['error' => 'Session invalide.'], 401);
        }

        $afterId = (int) $request->input('after', 0);

        $messages = WebChatMessage::where('session_id', $session->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get()
            ->map(fn (WebChatMessage $m) => $this->serializeMessage($m));

        return response()->json([
            'messages' => $messages,
            'status' => $session->status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /telegram-support/chat/file/{id}
    // Proxy to download an agent-sent Telegram file
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadFile(Request $request, int $messageId): Response
    {
        $session = $this->resolveSession($request);

        if (! $session) {
            abort(401);
        }

        $msg = WebChatMessage::where('id', $messageId)
            ->where('session_id', $session->id)
            ->firstOrFail();

        // User-uploaded file (stored locally)
        if ($msg->media_path) {
            $disk = Storage::disk('local');

            if (! $disk->exists($msg->media_path)) {
                abort(404);
            }

            return response($disk->get($msg->media_path), 200, [
                'Content-Type' => $msg->media_mime ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.($msg->media_name ?? 'file').'"',
            ]);
        }

        // Agent-sent file from Telegram — proxy download
        if ($msg->media_file_id) {
            $file = $this->bridge->downloadTelegramFile($msg->media_file_id);

            if (! $file) {
                abort(404);
            }

            $name = $msg->media_name ?? $file['name'];

            return response($file['content'], 200, [
                'Content-Type' => $file['mime'],
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]);
        }

        abort(404);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /telegram-support/widget.js  — embeddable widget script
    // ─────────────────────────────────────────────────────────────────────────

    public function widgetJs(): Response
    {
        $config = json_encode([
            'baseUrl' => mb_rtrim(config('app.url', ''), '/').'/telegram-support/chat',
            'title' => config('telegramlogs.web_chat.widget.title', 'Support'),
            'subtitle' => config('telegramlogs.web_chat.widget.subtitle', 'Nous répondons rapidement'),
            'color' => config('telegramlogs.web_chat.widget.color', '#0088CC'),
            'requireName' => config('telegramlogs.web_chat.widget.require_name', false),
            'placeholder' => config('telegramlogs.web_chat.widget.placeholder', 'Votre message...'),
            'welcomeMessage' => config('telegramlogs.web_chat.widget.welcome_message', 'Bonjour ! Comment pouvons-nous vous aider ?'),
            'pollInterval' => (int) config('telegramlogs.web_chat.poll_interval_ms', 3000),
        ]);

        $html = view('telegramlogs::webchat-widget', ['configJson' => $config])->render();

        // Wrap the Blade HTML as a self-executing JS snippet that injects it
        $js = <<<JS
(function() {
  var div = document.createElement('div');
  div.innerHTML = {$this->jsString($html)};
  document.body.appendChild(div.firstElementChild);
  while (div.firstChild) document.body.appendChild(div.firstChild);
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveSession(Request $request): ?WebChatSession
    {
        $token = $request->input('session_token')
            ?? $request->header('X-Chat-Token');

        if (! $token) {
            return null;
        }

        return WebChatSession::where('session_token', $token)->first();
    }

    private function serializeMessage(WebChatMessage $m): array
    {
        $base = [
            'id' => $m->id,
            'direction' => $m->direction,
            'content' => $m->content,
            'agent_name' => $m->agent_name,
            'created_at' => $m->created_at?->toISOString(),
        ];

        if ($m->hasMedia()) {
            $base['file'] = [
                'name' => $m->media_name ?? 'fichier',
                'mime' => $m->media_mime,
                'size' => $m->media_size,
                'url' => route('telegram.webchat.file', ['id' => $m->id]),
            ];
        }

        return $base;
    }

    private function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    private function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(strip_tags(mb_trim($value)), 0, 255) ?: null;
    }

    private function jsString(string $html): string
    {
        return json_encode($html, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}

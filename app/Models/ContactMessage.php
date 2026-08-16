<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    const STATUS_NEW      = 'new';
    const STATUS_READ     = 'read';
    const STATUS_REPLIED  = 'replied';
    const STATUS_SPAM     = 'spam';
    const STATUS_ARCHIVED = 'archived';

    const STATUS_LABELS = [
        self::STATUS_NEW      => 'New',
        self::STATUS_READ     => 'Read',
        self::STATUS_REPLIED  => 'Replied',
        self::STATUS_SPAM     => 'Spam',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    const SPAM_PATTERNS = [
        '/t\.me\//i',
        '/telegram\.me\//i',
        '/wa\.me\//i',
        '/api\.whatsapp\.com/i',
        '/earn\s+money/i',
        '/make\s+money\s+fast/i',
        '/crypto\s+investment/i',
        '/join\s+now/i',
        '/limited\s+time\s+offer/i',
        '/click\s+here\s+now/i',
        '/free\s+money/i',
        '/work\s+from\s+home.*earn/i',
        '/double\s+your\s+income/i',
        '/financial\s+freedom/i',
    ];

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'ip_address',
        'read',
        'read_at',
    ];

    protected $casts = [
        'read'    => 'boolean',
        'read_at' => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeNotSpam(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_SPAM);
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }

    // ── Methods ───────────────────────────────────────────────────

    public function markAsRead(): void
    {
        $this->update(['status' => self::STATUS_READ, 'read' => true, 'read_at' => now()]);
    }

    public function markAsSpam(): void
    {
        $this->update(['status' => self::STATUS_SPAM]);
    }

    public function markAsReplied(): void
    {
        $this->update(['status' => self::STATUS_REPLIED]);
    }

    public function archive(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    public static function isSpamContent(string $message): bool
    {
        foreach (self::SPAM_PATTERNS as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        // Flag if message contains more than 2 URLs
        preg_match_all('/https?:\/\//i', $message, $matches);
        if (count($matches[0]) > 2) {
            return true;
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusPageHealth;
use App\Enums\StatusPageTheme;
use Database\Factories\StatusPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'custom_domain',
    'headline',
    'description',
    'logo_url',
    'favicon_url',
    'footer_text',
    'custom_css',
    'theme',
    'published',
    'show_targets',
    'password',
    'refresh_seconds',
])]
#[Hidden(['password'])]
class StatusPage extends Model
{
    /** @use HasFactory<StatusPageFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'theme' => StatusPageTheme::class,
            'published' => 'boolean',
            'show_targets' => 'boolean',
            'password' => 'hashed',
            'refresh_seconds' => 'integer',
        ];
    }

    protected function customDomain(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => self::normalizeDomain($value),
        );
    }

    public function listings(): HasMany
    {
        return $this->hasMany(StatusPageMonitor::class)->orderBy('sort');
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'status_page_monitor')
            ->withPivot(['id', 'public_name', 'sort'])
            ->orderByPivot('sort')
            ->orderBy('name');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class)->orderByDesc('started_at');
    }

    public function isPasswordProtected(): bool
    {
        return filled($this->password);
    }

    public function passwordSessionKey(): string
    {
        return 'status-page.'.$this->id.'.unlocked';
    }

    public function health(): StatusPageHealth
    {
        $monitors = $this->relationLoaded('monitors') ? $this->monitors : $this->monitors()->get();
        MaintenanceWindow::primeMonitors($monitors);

        return StatusPageHealth::fromMonitorsAndIncidents(
            $monitors,
            $this->relationLoaded('incidents') ? $this->incidents : $this->incidents()->get(),
        );
    }

    public function pathUrl(): string
    {
        return url('/status/'.$this->slug);
    }

    public function publicUrl(): string
    {
        if (blank($this->custom_domain)) {
            return $this->pathUrl();
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$this->custom_domain;
    }

    public function incidentPath(Incident $incident, bool $onCustomDomain = false): string
    {
        if ($onCustomDomain) {
            return '/incidents/'.$incident->id;
        }

        return '/status/'.$this->slug.'/incidents/'.$incident->id;
    }

    /**
     * @param  Builder<StatusPage>  $query
     * @return Builder<StatusPage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public static function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return null;
        }

        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];
        $domain = trim($domain, '.');

        return $domain === '' ? null : $domain;
    }

    public static function hostMatchesApp(string $host): bool
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($appHost) || $appHost === '') {
            return false;
        }

        return strcasecmp(self::normalizeDomain($host) ?? '', self::normalizeDomain($appHost) ?? '') === 0;
    }
}

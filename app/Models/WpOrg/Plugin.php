<?php

namespace App\Models\WpOrg;

use App\Models\BaseModel;
use App\Models\Sync\SyncPlugin;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\WpOrg\PluginFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * @property-read string $id
 * @property string $slug
 * @property string $name
 * @property string $short_description
 * @property string $description
 * @property string $version
 * @property string $author
 * @property string $requires
 * @property string|null $requires_php
 * @property string $tested
 * @property string $download_link
 * @property CarbonImmutable $added
 * @property CarbonImmutable|null $last_updated
 * @property string|null $author_profile
 * @property int $rating
 * @property array|null $ratings
 * @property int $num_ratings
 * @property int $support_threads
 * @property int $support_threads_resolved
 * @property int $active_installs
 * @property int $downloaded
 * @property string|null $homepage
 * @property array|null $banners
 * @property string|null $donate_link
 * @property array|null $contributors
 * @property array|null $icons
 * @property array|null $source
 * @property string|null $business_model
 * @property string|null $commercial_support_url
 * @property string|null $support_url
 * @property string|null $preview_link
 * @property string|null $repository_url
 * @property array|null $requires_plugins
 * @property array|null $compatibility
 * @property array|null $screenshots
 * @property array|null $sections
 * @property array|null $versions
 * @property array|null $upgrade_notice
 * @property array<string, string> $tags
 */
final class Plugin extends BaseModel
{
    //region Definition

    use HasUuids;

    /** @use HasFactory<PluginFactory> */
    use HasFactory;

    protected $table = 'plugins';

    /** @phpstan-ignore-next-line */
    protected $appends = ['tags'];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'slug' => 'string',
            'name' => 'string',
            'short_description' => 'string',
            'description' => 'string',
            'version' => 'string',
            'author' => 'string',
            'requires' => 'string',
            'requires_php' => 'string',
            'tested' => 'string',
            'download_link' => 'string',
            'added' => 'immutable_datetime',
            'last_updated' => 'immutable_datetime',
            'author_profile' => 'string',
            'rating' => 'integer',
            'ratings' => 'array',
            'num_ratings' => 'integer',
            'support_threads' => 'integer',
            'support_threads_resolved' => 'integer',
            'active_installs' => 'integer',
            'downloaded' => 'integer',
            'homepage' => 'string',
            'banners' => 'array',
            'tags' => 'array',
            'donate_link' => 'string',
            'contributors' => 'array',
            'icons' => 'array',
            'source' => 'array',
            'business_model' => 'string',
            'commercial_support_url' => 'string',
            'support_url' => 'string',
            'preview_link' => 'string',
            'repository_url' => 'string',
            'requires_plugins' => 'array',
            'compatibility' => 'array',
            'screenshots' => 'array',
            'sections' => 'array',
            'versions' => 'array',
            'upgrade_notice' => 'array',
        ];
    }

    /** @return BelongsToMany<PluginTag, covariant self> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PluginTag::class, 'plugin_plugin_tags', 'plugin_id', 'plugin_tag_id', 'id', 'id');
    }

    /** @return BelongsTo<SyncPlugin, covariant static> */
    public function syncPlugin(): BelongsTo
    {
        return $this->belongsTo(SyncPlugin::class, 'sync_id', 'id');
    }

    //endregion

    //region Constructors

    public static function getOrCreateFromSyncPlugin(SyncPlugin $syncPlugin): self
    {
        return self::query()->firstWhere('sync_id', $syncPlugin->id) ?? self::createFromSyncPlugin($syncPlugin);
    }

    public static function createFromSyncPlugin(SyncPlugin $syncPlugin): self
    {
        $data = $syncPlugin->metadata or throw new InvalidArgumentException("SyncPlugin instance has no metadata");

        DB::beginTransaction();

        $instance = self::create([
            'sync_id' => $syncPlugin->id,
            'slug' => $syncPlugin->slug,
            'name' => $syncPlugin->name,
            'short_description' => self::truncate($data['short_description'] ?? '', 150),
            'description' => $data['description'] ?? '',
            'version' => $syncPlugin->current_version,
            'author' => self::truncate($data['author'] ?? '', 255),
            'requires' => $data['requires'] ?? '',
            'tested' => $data['tested'] ?? '',
            'download_link' => self::truncate($data['download_link'] ?? '', 1024),
            'added' => Carbon::parse($data['added']),
        ]);
        $instance->fillFromMetadata($data);
        $instance->save();

        DB::commit();
        return $instance;
    }

    //endregion

    //region Utilities

    /**
     * @param array<string,mixed> $data
     * @return $this
     */
    public function fillFromMetadata(array $data): self
    {
        if ($data['slug'] !== $this->slug) {
            throw new InvalidArgumentException("Metatada slug does not match [{$data['slug']} !== $this->slug]");
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $pluginTags = [];
            $this->tags()->detach();
            foreach ($data['tags'] as $tagSlug => $name) {
                $pluginTags[] = PluginTag::firstOrCreate(['slug' => $tagSlug], ['slug' => $tagSlug, 'name' => $name]);
            }
            $this->tags()->saveMany($pluginTags);
        }

        return $this->fill([
            'name' => $data['name'],
            'short_description' => self::truncate($data['short_description'] ?? '', 149),
            'description' => $data['description'] ?? '',
            'version' => $data['version'],
            'author' => self::truncate($data['author'] ?? '', 255),
            'requires' => $data['requires'],
            'requires_php' => $data['requires_php'] ?? null,
            'tested' => $data['tested'] ?? '',
            'download_link' => self::truncate($data['download_link'] ?? '', 1024),
            'added' => Carbon::parse($data['added']),
            'last_updated' => ($data['last_updated'] ?? null) ? Carbon::parse($data['last_updated']) : null,
            'author_profile' => $data['author_profile'] ?? null,
            'rating' => $data['rating'] ?? '',
            'ratings' => $data['ratings'] ?? null,
            'num_ratings' => $data['num_ratings'] ?? 0,
            'support_threads' => $data['support_threads'] ?? 0,
            'support_threads_resolved' => $data['support_threads_resolved'] ?? 0,
            'active_installs' => $data['active_installs'] ?? 0,
            'downloaded' => $data['downloaded'] ?? '',
            'homepage' => $data['homepage'] ?? null,
            'banners' => $data['banners'] ?? null,
            //            'tags' => $data['tags'] ?? null,
            'donate_link' => self::truncate($data['donate_link'] ?? null, 1024),
            'contributors' => $data['contributors'] ?? null,
            'icons' => $data['icons'] ?? null,
            'source' => $data['source'] ?? null,
            'business_model' => $data['business_model'] ?? null,
            'commercial_support_url' => self::truncate($data['commercial_support_url'] ?? null, 1024),
            'support_url' => self::truncate($data['support_url'] ?? null, 1024),
            'preview_link' => self::truncate($data['preview_link'] ?? null, 1024),
            'repository_url' => self::truncate($data['repository_url'] ?? null, 1024),
            'requires_plugins' => $data['requires_plugins'] ?? null,
            'compatibility' => $data['compatibility'] ?? null,
            'screenshots' => $data['screenshots'] ?? null,
            'sections' => $data['sections'] ?? null,
            'versions' => $data['versions'] ?? null,
            'upgrade_notice' => $data['upgrade_notice'] ?? null,
        ]);
    }

    /** @return $this */
    public function updateFromSyncPlugin(): self
    {
        return $this->fillFromMetadata($this->syncPlugin->metadata);
    }
    //endregion

    private static function truncate(?string $str, int $len): ?string
    {
        return $str === null ? $str : mb_substr($str, 0, $len, 'utf8');
    }

    /**
     * Get the tags attribute.
     *
     * @return array<string, string>
     */
    public function getTagsAttribute(): array
    {
        return $this->tags()
            ->get()
            ->pluck('name', 'slug')
            ->toArray();
    }
}

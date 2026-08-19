<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id', 'path', 'original_name', 'alt_text', 'is_primary', 'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    /**
     * Get the WebP URL if it exists on disk, null otherwise.
     */
    public function getWebpUrlAttribute(): ?string
    {
        $fullPath = Storage::disk('public')->path($this->path);
        $info = pathinfo($fullPath);
        $webpPath = $info['dirname'].'/'.$info['filename'].'.webp';

        if (file_exists($webpPath)) {
            $relative = str_replace(public_path(), '', $webpPath);

            return ltrim($relative, '/');
        }

        return null;
    }

    /**
     * Get srcset URLs keyed by width.
     *
     * @return array<int, string>
     */
    public function getSrcsetUrlsAttribute(): array
    {
        $result = [];
        $fullPath = Storage::disk('public')->path($this->path);
        $info = pathinfo($fullPath);
        $widths = config('image-optimization.srcset_widths', [400, 800]);

        foreach ($widths as $w) {
            $thumbPath = $info['dirname'].'/'.$info['filename'].'-'.$w.'.'.($info['extension'] ?: 'jpg');
            if (file_exists($thumbPath)) {
                $relative = str_replace(public_path(), '', $thumbPath);
                $result[$w] = ltrim($relative, '/');
            }
        }

        return $result;
    }

    /**
     * Generate a <picture> HTML element with WebP source, srcset, and lazy loading.
     */
    public function pictureTag(string $alt = '', string $classes = '', string $extra = ''): string
    {
        $url = $this->url;
        $webpUrl = $this->webp_url;
        $srcset = $this->srcset_urls;

        $srcsetAttribute = '';
        if (! empty($srcset)) {
            $entries = array_map(
                fn ($u, $w) => asset($u).' '.$w.'w',
                $srcset,
                array_keys($srcset)
            );
            $srcsetAttribute = ' srcset="'.htmlspecialchars(implode(', ', $entries)).'" sizes="(max-width: 600px) 400px, (max-width: 900px) 800px, 1200px"';
        }

        $lines = ['<picture>'];
        if ($webpUrl) {
            $lines[] = '  <source srcset="'.htmlspecialchars(asset($webpUrl)).'"'.$srcsetAttribute.' type="image/webp">';
        }
        if (! empty($srcset)) {
            $entries = array_map(
                fn ($u, $w) => asset($u).' '.$w.'w',
                $srcset,
                array_keys($srcset)
            );
            $lines[] = '  <source srcset="'.htmlspecialchars(implode(', ', $entries)).'" sizes="(max-width: 600px) 400px, (max-width: 900px) 800px, 1200px">';
        }
        $lines[] = '  <img src="'.htmlspecialchars($url).'" alt="'.htmlspecialchars($alt).'"'.($classes ? ' class="'.htmlspecialchars($classes).'"' : '').($extra ? ' '.$extra : '').' loading="lazy" decoding="async">';
        $lines[] = '</picture>';

        return implode("\n", $lines);
    }
}

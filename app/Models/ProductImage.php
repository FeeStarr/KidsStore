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
        $webpRelative = $this->pathToWebp($this->path);
        $fullPath = Storage::disk('public')->path($webpRelative);

        return file_exists($fullPath) ? Storage::url($webpRelative) : null;
    }

    /**
     * Get srcset URLs keyed by width.
     *
     * @return array<int, string>
     */
    public function getSrcsetUrlsAttribute(): array
    {
        $result = [];
        $widths = config('image-optimization.srcset_widths', [400, 800]);
        $info = pathinfo($this->path);
        $ext = $info['extension'] ?: 'jpg';

        foreach ($widths as $w) {
            $thumbRelative = $info['dirname'].'/'.$info['filename'].'-'.$w.'.'.$ext;
            $fullPath = Storage::disk('public')->path($thumbRelative);
            if (file_exists($fullPath)) {
                $result[$w] = Storage::url($thumbRelative);
            }
        }

        return $result;
    }

    private function pathToWebp(string $path): string
    {
        $info = pathinfo($path);
        return $info['dirname'].'/'.$info['filename'].'.webp';
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

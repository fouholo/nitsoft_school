<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

final class UniqueSlug
{
    /**
     * Slug dérivé du nom, suffixé (-1, -2…) tant qu'il est déjà pris. Les
     * lignes soft-deleted comptent : la contrainte unique en base les voit
     * encore.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function for(string $modelClass, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (self::taken($modelClass, $slug)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function taken(string $modelClass, string $slug): bool
    {
        // Sans effet sur un modèle sans SoftDeletes.
        return $modelClass::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('slug', $slug)
            ->exists();
    }
}

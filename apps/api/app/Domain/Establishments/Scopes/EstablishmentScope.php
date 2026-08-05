<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Isolation multi-tenant par défaut : toute requête sur un modèle tenant-aware
 * est restreinte à l'établissement courant, résolu par le middleware de tenant
 * (session pour le web, token Sanctum pour l'API/sync) et lié au container
 * sous la clé "currentEstablishmentId".
 *
 * Défense en profondeur : ce scope s'applique même si une Policy est oubliée
 * sur un endpoint — aucune fuite de données inter-établissements par défaut.
 */
class EstablishmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('currentEstablishmentId')) {
            $builder->where($model->qualifyColumn('establishment_id'), '=', app('currentEstablishmentId'));
        }
    }
}

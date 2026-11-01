<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Writes an audit row on create, update and delete. Models override
 * auditExcluded() to keep fields out of the log (tokens, noisy timestamps).
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            Audit::record($model->auditName('created'), $model, null, $model->auditFilter($model->getAttributes()));
        });

        static::updated(function (self $model): void {
            $changed = $model->auditFilter($model->getChanges());
            unset($changed['updated_at']);
            if ($changed === []) {
                return;
            }
            $before = array_intersect_key($model->auditFilter($model->getOriginal()), $changed);
            Audit::record($model->auditName('updated'), $model, $before, $changed);
        });

        static::deleted(function (self $model): void {
            Audit::record($model->auditName('deleted'), $model, $model->auditFilter($model->getOriginal()), null);
        });
    }

    /** @return list<string> extra attributes never written to the audit log */
    protected function auditExcluded(): array
    {
        return [];
    }

    protected function auditName(string $event): string
    {
        return strtolower(class_basename($this)).'.'.$event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditFilter(array $attributes): array
    {
        $exclude = ['password', 'remember_token', ...$this->auditExcluded()];

        return array_diff_key($attributes, array_flip($exclude));
    }
}

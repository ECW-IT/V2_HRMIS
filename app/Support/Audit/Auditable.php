<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/** Model trait. Hooks created/updated/deleted. */
trait Auditable
{
    // optional on the model: protected array $auditMasked = [];   // attributes stored masked
    // optional on the model: protected array $auditExcluded = []; // attributes never recorded

    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            $model->writeAuditLog('CREATED', null, $model->auditAttributeBag($model->getAttributes()));
        });

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = [];
            $new = [];

            foreach (array_keys($changes) as $key) {
                if ($model->shouldExcludeAuditAttribute($key)) {
                    continue;
                }

                $old[$key] = $model->normalizeAuditValue($model->getOriginal($key));
                $new[$key] = $model->normalizeAuditValue($changes[$key]);
            }

            if ($old === [] && $new === []) {
                return;
            }

            $model->writeAuditLog(
                'UPDATED',
                $model->maskAuditValues($old),
                $model->maskAuditValues($new),
            );
        });

        static::deleted(function ($model): void {
            $model->writeAuditLog(
                'DELETED',
                $model->auditAttributeBag($model->getAttributes()),
                null,
            );
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    protected function auditAttributeBag(array $attributes): ?array
    {
        $payload = [];

        foreach ($attributes as $key => $value) {
            if ($this->shouldExcludeAuditAttribute($key)) {
                continue;
            }

            $payload[$key] = $this->normalizeAuditValue($value);
        }

        return $this->maskAuditValues($payload);
    }

    protected function shouldExcludeAuditAttribute(string $key): bool
    {
        if (in_array($key, ['id', 'created_at', 'updated_at'], true)) {
            return true;
        }

        $excluded = property_exists($this, 'auditExcluded') ? $this->auditExcluded : [];

        return in_array($key, $excluded, true);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function maskAuditValues(array $values): array
    {
        $masked = property_exists($this, 'auditMasked') ? $this->auditMasked : [];

        foreach ($masked as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $values[$key] = $this->maskAuditValue($values[$key]);
        }

        return $values;
    }

    protected function maskAuditValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;

        if (mb_strlen($string) <= 4) {
            return '***';
        }

        return '***'.mb_substr($string, -4);
    }

    protected function normalizeAuditValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    protected function writeAuditLog(string $event, ?array $oldValues, ?array $newValues): void
    {
        $companyId = array_key_exists('company_id', $this->getAttributes())
            ? $this->getAttribute('company_id')
            : null;

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'company_id' => $companyId,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => AuditLogger::currentReason(),
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}

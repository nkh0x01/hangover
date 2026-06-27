<?php

declare(strict_types=1);

namespace App\Modules\Driver\Console;

use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Services\DriverApplicationApprovalService;
use Illuminate\Console\Command;

final class RepairApprovedApplicationsCommand extends Command
{
    protected $signature = 'drivers:repair-approved-applications {--dry-run : Show what would be repaired without writing changes}';

    protected $description = 'Repair approved driver applications missing linked driver or vehicle records.';

    public function handle(DriverApplicationApprovalService $approval): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $repaired = 0;
        $skipped = 0;

        DriverApplication::query()
            ->with(['driver', 'vehicle'])
            ->where('status', 'approved')
            ->orderBy('id')
            ->chunkById(100, function ($applications) use ($approval, $dryRun, &$checked, &$repaired, &$skipped): void {
                foreach ($applications as $application) {
                    $checked++;
                    $reasons = $this->repairReasons($application);

                    if ($reasons === []) {
                        continue;
                    }

                    if (! $this->canRepair($application)) {
                        $skipped++;
                        $this->warn(sprintf(
                            'skipped application_id=%d user_id=%d reason=%s',
                            $application->id,
                            $application->user_id,
                            implode(',', $reasons),
                        ));

                        continue;
                    }

                    if ($dryRun) {
                        $this->line(sprintf(
                            'would repair application_id=%d user_id=%d reason=%s',
                            $application->id,
                            $application->user_id,
                            implode(',', $reasons),
                        ));

                        continue;
                    }

                    $fixed = $approval->approve(
                        $application->fresh(['documents']) ?? $application,
                        reviewerUserId: $application->reviewed_by_user_id,
                    );

                    $repaired++;
                    $this->info(sprintf(
                        'repaired application_id=%d user_id=%d driver_id=%s vehicle_id=%s',
                        $fixed->id,
                        $fixed->user_id,
                        (string) $fixed->driver_id,
                        (string) ($fixed->vehicle_id ?? 'null'),
                    ));
                }
            });

        $this->info(sprintf(
            'checked=%d repaired=%d skipped=%d dry_run=%s',
            $checked,
            $repaired,
            $skipped,
            $dryRun ? 'yes' : 'no',
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function repairReasons(DriverApplication $application): array
    {
        $application->loadMissing(['driver', 'vehicle']);

        $reasons = [];

        if ($application->driver === null) {
            $reasons[] = 'missing_driver';
        }

        if ($this->hasVehicleData($application)) {
            $currentVehicleId = $application->driver?->current_vehicle_id;
            if ($application->vehicle === null || $currentVehicleId === null || $currentVehicleId !== $application->vehicle_id) {
                $reasons[] = 'missing_vehicle_link';
            }
        }

        return $reasons;
    }

    private function canRepair(DriverApplication $application): bool
    {
        if ($application->driver === null) {
            return true;
        }

        return $this->hasVehicleData($application);
    }

    private function hasVehicleData(DriverApplication $application): bool
    {
        return filled($application->vehicle_type)
            && filled($application->vehicle_brand)
            && filled($application->vehicle_model)
            && filled($application->vehicle_year)
            && filled($application->vehicle_color)
            && filled($application->vehicle_plate);
    }
}

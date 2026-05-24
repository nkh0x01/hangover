<?php

declare(strict_types=1);

namespace App\Modules\Driver\Services;

use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Identity\Models\User;

final class DriverApplicationStatusDecider
{
    /**
     * @return array{
     *   status: string,
     *   reason: ?string,
     *   missing_fields: list<string>,
     *   missing_documents: list<string>,
     *   manual_review_reasons: list<string>,
     *   can_auto_approve: bool
     * }
     */
    public function decide(DriverApplication $application, User $user): array
    {
        $application->loadMissing('documents');

        $missingFields = $this->missingFields($application);
        $missingDocuments = $this->missingDocuments($application);
        $manualReviewReasons = $this->manualReviewReasons($application, $user);

        if ($missingFields !== [] || $missingDocuments !== []) {
            return [
                'status' => 'needs_completion',
                'reason' => 'application.incomplete',
                'missing_fields' => $missingFields,
                'missing_documents' => $missingDocuments,
                'manual_review_reasons' => $manualReviewReasons,
                'can_auto_approve' => false,
            ];
        }

        if ($manualReviewReasons !== []) {
            return [
                'status' => 'manual_review',
                'reason' => 'application.manual_review',
                'missing_fields' => [],
                'missing_documents' => [],
                'manual_review_reasons' => $manualReviewReasons,
                'can_auto_approve' => false,
            ];
        }

        $canAutoApprove = (bool) config('drivers.applications.auto_approve', false);

        return [
            'status' => $canAutoApprove ? 'approved' : 'pending',
            'reason' => $canAutoApprove ? 'application.auto_approved' : 'application.pending_review',
            'missing_fields' => [],
            'missing_documents' => [],
            'manual_review_reasons' => [],
            'can_auto_approve' => $canAutoApprove,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function requiredFields(): array
    {
        return [
            'first_name' => 'სახელი',
            'last_name' => 'გვარი',
            'personal_id' => 'პირადი ნომერი',
            'phone_e164' => 'ტელეფონი',
            'city_id' => 'ქალაქი',
            'driver_type' => 'მძღოლის ტიპი',
            'vehicle_type' => 'ტრანსპორტის ტიპი',
            'vehicle_brand' => 'ბრენდი',
            'vehicle_model' => 'მოდელი',
            'vehicle_year' => 'წელი',
            'vehicle_color' => 'ფერი',
            'vehicle_plate' => 'სახელმწიფო ნომერი',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function requiredConsents(): array
    {
        return [
            'information_confirmed' => 'ინფორმაციის სისწორის დადასტურება',
            'terms_accepted' => 'წესებზე თანხმობა',
            'privacy_accepted' => 'მონაცემთა დამუშავებაზე თანხმობა',
        ];
    }

    /**
     * @return list<string>
     */
    public function requiredDocumentTypes(): array
    {
        return ['id_front', 'id_back', 'license_front', 'license_back', 'vehicle_registration', 'vehicle_photo', 'selfie'];
    }

    /**
     * @return list<string>
     */
    public function missingFields(DriverApplication $application): array
    {
        $missing = [];

        foreach (array_keys($this->requiredFields()) as $field) {
            if (blank($application->{$field})) {
                $missing[] = $field;
            }
        }

        foreach (array_keys($this->requiredConsents()) as $field) {
            if ($application->{$field} !== true) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    public function missingDocuments(DriverApplication $application): array
    {
        $application->loadMissing('documents');

        $uploaded = $application->documents
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('doc_type')
            ->all();

        return array_values(array_diff($this->requiredDocumentTypes(), $uploaded));
    }

    /**
     * @return list<string>
     */
    private function manualReviewReasons(DriverApplication $application, User $user): array
    {
        $reasons = [];

        if ($user->isBlocked()) {
            $reasons[] = 'blocked_user';
        }

        if (filled($application->personal_id) && DriverApplication::query()
            ->where('personal_id', $application->personal_id)
            ->whereKeyNot($application->id)
            ->exists()) {
            $reasons[] = 'duplicate_personal_id';
        }

        if (filled($application->phone_e164) && User::query()
            ->where('phone_e164', $application->phone_e164)
            ->whereKeyNot($user->id)
            ->exists()) {
            $reasons[] = 'duplicate_phone';
        }

        return $reasons;
    }
}

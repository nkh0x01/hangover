<?php

namespace App\Services\Gadget\Resources;

use App\Services\Gadget\WooCommerceClient;

class Orders
{
    public function __construct(private WooCommerceClient $client) {}

    public function create(array $payload): array
    {
        return $this->client->post('orders', $payload);
    }

    public function get(int $id): ?array
    {
        $o = $this->client->get("orders/$id");
        return $o ?: null;
    }

    public function updateStatus(int $id, string $status): array
    {
        return $this->client->put("orders/$id", ['status' => $status]);
    }

    public function addNote(int $id, string $note, bool $customerVisible = false): array
    {
        return $this->client->post("orders/$id/notes", [
            'note'          => $note,
            'customer_note' => $customerVisible,
        ]);
    }
}

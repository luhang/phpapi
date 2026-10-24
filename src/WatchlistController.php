<?php

declare(strict_types=1);

namespace App;

use PDOException;

final class WatchlistController
{
    public function __construct(private readonly WatchlistRepository $repo)
    {
    }

    public function index(array $query): void
    {
        $result = $this->repo->query($query);
        Response::success($result);
    }

    public function show(string $exchangeCode, string $ticker): void
    {
        $row = $this->repo->find($exchangeCode, $ticker);
        if ($row === null) {
            Response::error('Watchlist item not found', 404);
        }

        Response::success($row);
    }

    public function store(array $body): void
    {
        $errors = $this->validate($body);
        if ($errors !== []) {
            Response::error('Validation failed', 422, $errors);
        }

        try {
            $row = $this->repo->create($body);
            Response::success($row, 'created', 201);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                Response::error('Already in watchlist', 409);
            }
            Response::error('Failed to create watchlist item', 500);
        }
    }

    public function destroy(string $exchangeCode, string $ticker): void
    {
        if (!$this->repo->delete($exchangeCode, $ticker)) {
            Response::error('Watchlist item not found', 404);
        }

        Response::success(null, 'deleted');
    }

    /**
     * @return list<string>
     */
    private function validate(array $body): array
    {
        $errors = [];

        if (empty($body['exchange_code']) || !is_string($body['exchange_code'])) {
            $errors[] = 'exchange_code is required';
        } elseif (strlen(trim($body['exchange_code'])) > 16) {
            $errors[] = 'exchange_code must be <= 16 characters';
        }

        if (empty($body['ticker']) || !is_string($body['ticker'])) {
            $errors[] = 'ticker is required';
        } elseif (strlen(trim($body['ticker'])) > 32) {
            $errors[] = 'ticker must be <= 32 characters';
        }

        return $errors;
    }
}

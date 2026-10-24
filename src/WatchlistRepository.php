<?php

declare(strict_types=1);

namespace App;

use PDO;

final class WatchlistRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * List / search watchlist rows with optional filters and pagination.
     *
     * @param array{
     *   exchange_code?: string,
     *   ticker?: string,
     *   keyword?: string,
     *   page?: int|string,
     *   page_size?: int|string,
     *   order_by?: string,
     *   order_dir?: string
     * } $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, page_size: int}
     */
    public function query(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['exchange_code'])) {
            $where[]                 = 'exchange_code = :exchange_code';
            $params['exchange_code'] = strtoupper(trim((string) $filters['exchange_code']));
        }

        if (!empty($filters['ticker'])) {
            $where[]           = 'ticker = :ticker';
            $params['ticker']  = strtoupper(trim((string) $filters['ticker']));
        }

        if (!empty($filters['keyword'])) {
            $where[]           = '(exchange_code LIKE :keyword OR ticker LIKE :keyword)';
            $params['keyword'] = '%' . trim((string) $filters['keyword']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $allowedOrder = ['exchange_code', 'ticker', 'created_at'];
        $orderBy      = in_array($filters['order_by'] ?? '', $allowedOrder, true)
            ? $filters['order_by']
            : 'created_at';
        $orderDir     = strtoupper((string) ($filters['order_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $page     = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['page_size'] ?? 20)));
        $offset   = ($page - 1) * $pageSize;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM watchlist WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT exchange_code, ticker, created_at
                FROM watchlist
                WHERE {$whereSql}
                ORDER BY {$orderBy} {$orderDir}, exchange_code ASC, ticker ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items'     => $stmt->fetchAll(),
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    public function find(string $exchangeCode, string $ticker): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT exchange_code, ticker, created_at
             FROM watchlist
             WHERE exchange_code = :exchange_code AND ticker = :ticker
             LIMIT 1'
        );
        $stmt->execute([
            'exchange_code' => strtoupper(trim($exchangeCode)),
            'ticker'        => strtoupper(trim($ticker)),
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): array
    {
        $exchangeCode = strtoupper(trim((string) $data['exchange_code']));
        $ticker       = strtoupper(trim((string) $data['ticker']));

        $stmt = $this->db->prepare(
            'INSERT INTO watchlist (exchange_code, ticker) VALUES (:exchange_code, :ticker)'
        );
        $stmt->execute([
            'exchange_code' => $exchangeCode,
            'ticker'        => $ticker,
        ]);

        return $this->find($exchangeCode, $ticker) ?? [
            'exchange_code' => $exchangeCode,
            'ticker'        => $ticker,
        ];
    }

    public function delete(string $exchangeCode, string $ticker): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM watchlist WHERE exchange_code = :exchange_code AND ticker = :ticker'
        );
        $stmt->execute([
            'exchange_code' => strtoupper(trim($exchangeCode)),
            'ticker'        => strtoupper(trim($ticker)),
        ]);

        return $stmt->rowCount() > 0;
    }
}

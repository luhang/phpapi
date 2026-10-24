-- Database: stock1
-- Table: watchlist

USE `stock1`;

CREATE TABLE IF NOT EXISTS `watchlist` (
  `exchange_code` VARCHAR(16)  NOT NULL COMMENT '交易所代码',
  `ticker`        VARCHAR(32)  NOT NULL COMMENT '股票代码',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`exchange_code`, `ticker`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='关注股票列表';

-- Test data
INSERT INTO `watchlist` (`exchange_code`, `ticker`) VALUES
  ('XSHG', '600519'),
  ('XSHG', '601318'),
  ('XSHG', '600036'),
  ('XSHE', '000001'),
  ('XSHE', '000858'),
  ('XSHE', '300750'),
  ('XNAS', 'AAPL'),
  ('XNAS', 'MSFT'),
  ('XNYS', 'BABA'),
  ('XHKG', '00700')
ON DUPLICATE KEY UPDATE `created_at` = `created_at`;

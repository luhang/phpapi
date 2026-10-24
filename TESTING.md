# 测试环境搭建与测试方法

本文说明如何在本地搭建 PHP Watchlist API 的测试环境，以及如何手工验证各接口。

## 1. 环境要求

| 组件 | 建议版本 | 说明 |
|------|----------|------|
| PHP | 8.2+ | 需启用 `pdo_mysql`、`mysqli` |
| MySQL | 8.0+ | 测试库名：`stock1` |
| curl | 任意 | 命令行调用 API（也可用 Postman / Insomnia） |

检查 PHP 扩展：

```bash
php -m | findstr /i "pdo_mysql mysqli"
```

若未启用，在 `php.ini` 中取消注释：

```ini
extension=pdo_mysql
extension=mysqli
```

## 2. 搭建测试环境

### 2.1 获取代码

```bash
git clone https://github.com/luhang/phpapi.git
cd phpapi
```

### 2.2 配置数据库连接

```bash
copy .env.example .env
```

编辑 `.env`，填写本机 MySQL 信息：

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=stock1
DB_USER=root
DB_PASS=你的密码
APP_DEBUG=1
```

`APP_DEBUG=1` 时，接口错误响应会带上详细异常信息，便于排查；上线请设为 `0`。

### 2.3 创建库表与测试数据

先确保 MySQL 中已有数据库 `stock1`：

```sql
CREATE DATABASE IF NOT EXISTS stock1
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
```

再导入表结构与样例数据：

```bash
mysql -u root -p --default-character-set=utf8mb4 < sql/watchlist.sql
```

导入成功后应有 10 条测试记录（沪深 / 美股 / 港股各若干）。

验证：

```sql
USE stock1;
SELECT COUNT(*) FROM watchlist;   -- 期望 10
SELECT * FROM watchlist ORDER BY exchange_code, ticker;
```

### 2.4 启动 API 服务

在项目根目录执行（文档根目录为 `public`）：

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

浏览器或终端访问健康检查：

```bash
curl http://127.0.0.1:8080/health
```

期望响应：

```json
{"success":true,"message":"ok","data":{"service":"stock-watchlist-api","status":"up"}}
```

若返回 `Database connection failed`，请核对 `.env` 中的主机、库名、账号密码，以及 MySQL 服务是否已启动。

---

## 3. API 一览

基础地址：`http://127.0.0.1:8080`  
也支持前缀：`/api/watchlist`（与 `/watchlist` 等价）。

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/health` | 健康检查 |
| GET | `/watchlist` | 列表查询（支持筛选、分页） |
| GET | `/watchlist/{exchange_code}/{ticker}` | 单条查询 |
| POST | `/watchlist` | 新增 |
| DELETE | `/watchlist/{exchange_code}/{ticker}` | 删除 |

### 列表查询参数

| 参数 | 示例 | 说明 |
|------|------|------|
| `exchange_code` | `XSHG` | 按交易所过滤 |
| `ticker` | `600519` | 按代码精确匹配 |
| `keyword` | `60` | 对 `exchange_code` / `ticker` 模糊搜索 |
| `page` | `1` | 页码，默认 1 |
| `page_size` | `20` | 每页条数，1–100，默认 20 |
| `order_by` | `created_at` | `exchange_code` / `ticker` / `created_at` |
| `order_dir` | `DESC` | `ASC` 或 `DESC` |

---

## 4. 测试方法（手工）

以下命令假设服务已在 `127.0.0.1:8080` 运行。Windows 可用 `curl.exe`。

### 4.1 健康检查

```bash
curl -s http://127.0.0.1:8080/health
```

**通过标准：** `success` 为 `true`，`status` 为 `up`。

### 4.2 查询全部（分页）

```bash
curl -s "http://127.0.0.1:8080/watchlist?page=1&page_size=5"
```

**通过标准：**

- HTTP 200
- `data.total` ≥ 10（导入样例后）
- `data.items` 长度为 5
- 每条含 `exchange_code`、`ticker`、`created_at`

### 4.3 按交易所过滤

```bash
curl -s "http://127.0.0.1:8080/watchlist?exchange_code=XSHG"
```

**通过标准：** 返回的条目 `exchange_code` 均为 `XSHG`（样例中应为 3 条）。

### 4.4 按代码精确查询

```bash
curl -s "http://127.0.0.1:8080/watchlist?ticker=AAPL"
```

或单条路径：

```bash
curl -s "http://127.0.0.1:8080/watchlist/XNAS/AAPL"
```

**通过标准：** 返回 `XNAS` / `AAPL`；不存在时 HTTP 404，`success` 为 `false`。

### 4.5 关键词模糊搜索

```bash
curl -s "http://127.0.0.1:8080/watchlist?keyword=00"
```

**通过标准：** 命中含 `00` 的 ticker（如 `000001`、`000858`、`00700` 等）。

### 4.6 新增

```bash
curl -s -X POST http://127.0.0.1:8080/watchlist ^
  -H "Content-Type: application/json" ^
  -d "{\"exchange_code\":\"XSHG\",\"ticker\":\"601012\"}"
```

Linux / macOS：

```bash
curl -s -X POST http://127.0.0.1:8080/watchlist \
  -H "Content-Type: application/json" \
  -d '{"exchange_code":"XSHG","ticker":"601012"}'
```

**通过标准：**

- 首次：HTTP 201，`message` 为 `created`，响应中带回刚插入的记录
- 重复插入同一主键：HTTP 409，`Already in watchlist`
- 缺字段：HTTP 422，`Validation failed`

### 4.7 删除

```bash
curl -s -X DELETE http://127.0.0.1:8080/watchlist/XSHG/601012
```

**通过标准：**

- 存在：HTTP 200，`message` 为 `deleted`
- 再删一次：HTTP 404

### 4.8 建议回归顺序

1. `/health` → 确认服务与库连通  
2. `GET /watchlist` → 确认样例数据可读  
3. 带 `exchange_code` / `ticker` / `keyword` 过滤 → 确认查询逻辑  
4. `POST` 新增一条专用测试代码 → 再 `GET` 单条确认  
5. `DELETE` 该测试代码 → 再 `GET` 确认 404  
6. 重复 `POST` 同一条 → 确认 409  

完成后数据库应恢复为导入脚本中的 10 条样例（若未改动其它行）。

---

## 5. 常见问题

| 现象 | 处理 |
|------|------|
| `Unknown database 'stock1'` | 先建库再执行 `sql/watchlist.sql` |
| `Access denied` | 检查 `.env` 用户名/密码 |
| `could not find driver` | 启用 PHP 的 `pdo_mysql` |
| 改 `.env` 不生效 | 确认文件在项目根目录；内置服务器每个请求会重新加载 |
| 404 Not found | 确认使用了 `public/router.php` 启动，路径为 `/watchlist` 而非文件名 |

---

## 6. 重置测试数据

需要清空并重新灌入样例时：

```sql
USE stock1;
TRUNCATE TABLE watchlist;
```

然后再次执行：

```bash
mysql -u root -p --default-character-set=utf8mb4 < sql/watchlist.sql
```

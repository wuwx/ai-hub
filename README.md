# AI Hub

AI Hub 是一个基于 Laravel 13 + Filament 5 + Livewire 4 构建的 LLM API 管理与运营平台。

它提供统一的 OpenAI / Anthropic 兼容网关、团队级鉴权与配额、用量统计、账单生成、Stripe 自动收款与订阅联动能力，目标是让团队快速搭建可商业化运营的 AI 接口服务。

## 核心能力

- 统一兼容网关
  - OpenAI: `/api/v1/chat/completions`, `/api/v1/responses`
  - Anthropic: `/api/v1/messages`
- 多租户团队治理
  - Team 维度 API Key
  - Provider / Model 授权（entitlements）
  - Team 级配额策略（日/周/月）
- 可靠性与风控
  - API Key 鉴权（Bearer 或 `x-api-key`）
  - API Key 级限流
  - 幂等键缓存与冲突保护
  - 上游重试与熔断
  - 全链路 `X-Trace-Id`
- 运维观测
  - 请求日志（request logs）
  - 用量台账（day/week/month）
  - Filament 后台统计看板
- 商业化能力
  - 月度按用量出账（invoice + items）
  - Stripe Checkout 支付链接生成
  - Stripe Webhook 自动核销
  - 退款回写账单状态
  - 订阅套餐联动配额
  - 逾期账单自动标记

## 技术栈

- PHP 8.5
- Laravel 13
- Filament 5
- Livewire 4
- Fortify
- Pest 4
- Tailwind CSS v4

## 项目结构（简要）

- `app/Actions/Gateway`: 网关处理、协议转换、重试熔断、幂等
- `app/Actions/Usage`: 用量记账与配额校验
- `app/Actions/Billing`: 出账、支付、订阅配额同步
- `app/Http/Middleware`: API Key 鉴权、限流
- `app/Http/Controllers/Billing`: Stripe Webhook 入站
- `app/Filament`: 管理后台资源与运营看板
- `database/migrations`: 网关、用量、配额、账单相关表结构
- `tests/Feature`: 网关/用量/计费/后台功能测试

## 快速开始

### 1. 安装依赖

```bash
composer install
npm install
```

### 2. 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

### 3. 数据库迁移

```bash
php artisan migrate
```

### 4. 启动开发环境

```bash
composer run dev
```

如果你使用分离进程，也可以分别执行：

```bash
php artisan serve
npm run dev
```

## 关键环境变量

### 网关

- `LLM_GATEWAY_TIMEOUT_SECONDS`
- `LLM_GATEWAY_ANTHROPIC_VERSION`
- `LLM_GATEWAY_RETRY_ATTEMPTS`
- `LLM_GATEWAY_RETRY_BACKOFF_MS`
- `LLM_GATEWAY_CIRCUIT_FAILURE_THRESHOLD`
- `LLM_GATEWAY_CIRCUIT_COOLDOWN_SECONDS`
- `LLM_GATEWAY_IDEMPOTENCY_TTL_SECONDS`
- `LLM_GATEWAY_API_KEY_RATE_LIMIT_PER_MINUTE`
- `LLM_GATEWAY_API_KEY_RATE_LIMIT_DECAY_SECONDS`

### 计费与支付

- `BILLING_CURRENCY`
- `BILLING_INVOICE_DUE_DAYS`
- `BILLING_FREE_PLAN_CODE`
- `BILLING_CHECKOUT_SUCCESS_URL`
- `BILLING_CHECKOUT_CANCEL_URL`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_WEBHOOK_TOLERANCE_SECONDS`

## API 网关示例

### OpenAI 兼容

```bash
curl -X POST http://127.0.0.1:8000/api/v1/chat/completions \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "qwen3:0.6b",
    "messages": [{"role": "user", "content": "你好"}],
    "stream": false
  }'
```

### Anthropic 兼容

```bash
curl -X POST http://127.0.0.1:8000/api/v1/messages \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4.1",
    "messages": [{"role": "user", "content": "hello"}],
    "max_tokens": 128
  }'
```

## 运营命令

### 生成月账单

```bash
php artisan billing:generate-monthly-invoices --month=2026-06
```

可选按团队生成：

```bash
php artisan billing:generate-monthly-invoices --month=2026-06 --team-id=1
```

### 标记逾期账单

```bash
php artisan billing:mark-overdue-invoices
```

建议加入计划任务（crontab / scheduler）周期执行。

当前项目默认调度策略：

- 每月 1 日 01:10 自动执行上一月出账
- 每小时执行一次逾期状态扫描

你仍需在服务器配置系统级 crontab（每分钟触发 Laravel scheduler）：

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Stripe Webhook

请在 Stripe 控制台配置 Webhook 目标地址：

- `POST /api/webhooks/stripe`

系统当前处理的事件包括：

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `invoice.paid`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `charge.refunded`
- `charge.refund.updated`

退款状态语义：

- 全额退款：账单状态置为 `void`
- 部分退款：保留 `paid`，并在账单备注记录退款事件

## 测试

运行全量测试：

```bash
php artisan test --compact
```

运行核心平台回归：

```bash
php artisan test --compact tests/Feature/Gateway tests/Feature/Usage tests/Feature/ApiKeys tests/Feature/Billing tests/Feature/LlmPlatformAdminSetupTest.php
```

## 管理后台

使用 Filament 进行运营管理（Provider / Model / Entitlement / Quota / API Key / Logs / Billing）。

在本地启动后访问：

- `/admin`

## 生产建议

- 配置 Redis 作为缓存与限流存储
- 开启队列与失败重试策略
- 为 webhook 增加公网 TLS 与源地址策略
- 将 `billing:generate-monthly-invoices` 与 `billing:mark-overdue-invoices` 纳入定时任务
- 对 `X-Trace-Id` 做日志与 APM 关联

## License

Proprietary (internal/commercial use by project owner).

# AI Hub

AI Hub 是一个基于 Laravel 13 + Filament 5 + Livewire 4 构建的 LLM API 管理与运营平台。

它提供统一的 OpenAI / Anthropic 兼容网关、团队级鉴权与配额、用量统计、预付费钱包实时扣费、月度账单生成、Stripe 自动收款与订阅联动能力，目标是让团队快速搭建可商业化运营的 AI 接口服务。

## 核心能力

- 统一兼容网关
  - OpenAI: `/api/v1/chat/completions`, `/api/v1/responses`
  - Anthropic: `/api/v1/messages`
  - 流式（SSE）与非流式双通道，流式响应按真实输出 token 计费
- 多租户团队治理
  - Team 维度 API Key
  - Provider / Model 授权（entitlements）
  - Team 级配额策略（日/周/月）
  - API Key 细粒度权限：模型白名单、日 token 限额、每分钟限流
- 可靠性与风控
  - API Key 鉴权（Bearer 或 `x-api-key`）
  - 幂等键缓存与冲突保护
  - 上游重试与熔断（Circuit Breaker）
  - Provider 健康检查（每 5 分钟轮询，自动熔断/恢复）
  - 异常用量检测（高错误率告警，去重防刷）
  - 配额阈值告警（80% 自动通知 team owner）
  - 全链路 `X-Trace-Id`
- 运维观测
  - 请求日志（request logs，30 天自动清理）
  - 用量台账（day/week/month 三粒度）
  - Filament 后台统计看板
- 商业化能力
  - **预付费钱包**：Stripe Checkout 充值 → 实时扣费（成本 + markup 利润）
  - **后付费订阅**：Stripe Subscription 联动配额，月度出账
  - 月度按用量出账（invoice + items），自动跳过预付费团队避免重复计费
  - Stripe Webhook 自动核销（支付、退款、订阅状态同步）
  - 退款回写账单状态（全额 → void，部分 → 备注记录）
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
- `app/Actions/Usage`: 用量记账、配额校验、阈值告警
- `app/Actions/Billing`: 钱包扣费/充值、出账、支付、订阅配额同步、定价
- `app/Actions/ApiKeys`: API Key 生成、轮换
- `app/Http/Middleware`: API Key 鉴权、限流
- `app/Http/Controllers/Billing`: Stripe Webhook、钱包充值端点
- `app/Console/Commands`: 月度出账、逾期标记、Provider 健康检查、日志清理、异常检测
- `app/Filament`: 管理后台资源与运营看板
- `app/Notifications/Teams`: 配额告警、异常检测、团队邀请通知
- `database/migrations`: 网关、用量、配额、账单、钱包相关表结构
- `tests/Feature`: 网关/用量/计费/后台/团队功能测试（180 个测试用例）

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

### 上游 Provider 密钥

在 `llm_providers.secret_ref` 中以 `secret://KEY` 引用（推荐，兼容 `config:cache`）：

- `OPENAI_API_KEY`
- `ANTHROPIC_API_KEY`
- `GROQ_API_KEY`
- `DEEPSEEK_API_KEY`
- `MISTRAL_API_KEY`

### 计费与支付

- `BILLING_CURRENCY`
- `BILLING_INVOICE_DUE_DAYS`
- `BILLING_FREE_PLAN_CODE`
- `BILLING_CHECKOUT_SUCCESS_URL`
- `BILLING_CHECKOUT_CANCEL_URL`
- `BILLING_WALLET_RECHARGE_SUCCESS_URL`
- `BILLING_WALLET_RECHARGE_CANCEL_URL`
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

### 钱包充值（客户自服务）

```bash
curl -X POST http://127.0.0.1:8000/{team_slug}/billing/wallet/recharge \
  -H "Authorization: Bearer <SESSION_COOKIE>" \
  -H "Content-Type: application/json" \
  -d '{
    "amount_cents": 5000,
    "currency": "USD"
  }'
```

返回 Stripe Checkout 跳转链接，支付成功后 Webhook 自动入账钱包。

## 运营命令

### 生成月账单

```bash
# 默认只为后付费团队生成（预付费团队已通过钱包实时扣费）
php artisan billing:generate-monthly-invoices --month=2026-06

# 包含预付费团队（仅作对账用途）
php artisan billing:generate-monthly-invoices --month=2026-06 --include-prepaid

# 按团队生成
php artisan billing:generate-monthly-invoices --month=2026-06 --team-id=1
```

### 标记逾期账单

```bash
php artisan billing:mark-overdue-invoices
```

### Provider 健康检查

```bash
php artisan gateway:check-provider-health
```

### 异常用量检测

```bash
php artisan gateway:detect-anomalous-usage --window=60 --min-requests=50 --error-rate=50 --dedupe-hours=6
```

### 清理过期请求日志

```bash
php artisan gateway:prune-request-logs --days=30
```

### 定时任务

当前项目默认调度策略（`routes/console.php`）：

- 每月 1 日 01:10 自动执行上一月出账（跳过预付费团队）
- 每小时执行逾期账单扫描
- 每 5 分钟执行 Provider 健康检查
- 每小时执行异常用量检测（去重 6 小时）
- 每天 02:30 清理 30 天前的请求日志
- 每天清理过期团队邀请

你需要在服务器配置系统级 crontab（每分钟触发 Laravel scheduler）：

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Stripe Webhook

请在 Stripe 控制台配置 Webhook 目标地址：

- `POST /api/webhooks/stripe`

系统当前处理的事件包括：

- `checkout.session.completed`（发票支付 / 钱包充值，按 metadata 自动区分）
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

## 计费模型

### 预付费（推荐）

1. 客户在 dashboard 发起充值 → Stripe Checkout 支付
2. Webhook 回调自动入账团队钱包（幂等）
3. 网关每次请求实时扣费（成本 + markup 利润）
4. 余额不足时预检拦截，返回 HTTP 402

### 后付费

1. 客户订阅 Stripe 计划 → Webhook 同步订阅状态
2. 订阅激活时自动创建后付费钱包（允许余额为负）
3. 网关每次请求实时扣费（余额变负代表欠款）
4. 月底自动生成发票 → Stripe Checkout 收款

### 定价配置

每个 `LlmModel` 支持三种定价模式（优先级递减）：

1. `sell_input_per_1m_usd` / `sell_output_per_1m_usd`：直接设定售价
2. `markup_percent` + `cost_input_per_1m_usd` / `cost_output_per_1m_usd`：成本加成
3. `pricing` JSON（legacy）：兼容旧格式

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

使用 Filament 进行运营管理（Provider / Model / Entitlement / Quota / API Key / Logs / Billing / Wallet）。

在本地启动后访问：

- `/admin`

## 生产建议

- 配置 Redis 作为缓存与限流存储
- 开启队列与失败重试策略
- 为 webhook 增加公网 TLS 与源地址策略
- 将所有定时命令纳入 Laravel scheduler
- 对 `X-Trace-Id` 做日志与 APM 关联
- 监控 `gateway.wallet.debit_failed` 日志，及时发现余额异常
- 定期审计 `team_wallet_transactions` 流水

## License

Proprietary (internal/commercial use by project owner).

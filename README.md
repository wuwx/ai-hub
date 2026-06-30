# AI Hub

AI Hub 是一个基于 Laravel 13 + Filament 5 + Livewire 4 构建的 LLM API 管理与运营平台。

它提供统一的 OpenAI / Anthropic 兼容网关、团队级鉴权与配额、用量统计、预付费钱包实时扣费、月度账单生成、Stripe 自动收款与订阅联动能力，目标是让团队快速搭建可商业化运营的 AI 接口服务。

## 核心能力

- 统一兼容网关
  - OpenAI: `/api/v1/chat/completions`, `/api/v1/responses`, `/api/v1/embeddings`
  - Anthropic: `/api/v1/messages`
  - 流式（SSE）与非流式双通道，流式响应按真实输出 token 计费
- 多租户团队治理
  - Team 维度 API Key
  - Provider / Model 授权（entitlements）
  - Team 级配额策略（日/周/月 token 限额 + 日消费金额上限）
  - API Key 细粒度权限：模型白名单、IP 白名单（支持 CIDR）、日 token 限额、每分钟限流
  - 团队所有权转移（Owner → Admin 降级 + 新 Owner 提升）
  - 审计日志（Audit Log）：记录 API Key 创建/吊销/轮换、成员邀请/移除、所有权转移等关键操作
- 可靠性与风控
  - API Key 鉴权（Bearer 或 `x-api-key`）
  - 幂等键缓存与冲突保护
  - 上游重试与熔断（Circuit Breaker）
  - 模型降级（Model Fallback）：当模型不可用（熔断/5xx）时自动重试到配置的 `fallback_model_id`
  - Provider 健康检查（每 5 分钟轮询，自动熔断/恢复）
  - 异常用量检测（高错误率告警，去重防刷）
  - 配额阈值告警（80% 自动通知 team owner）
  - 全链路 `X-Trace-Id`
  - 标准 Rate Limit 响应头（`X-RateLimit-Limit` / `X-RateLimit-Remaining` / `X-RateLimit-Reset`）
  - 团队级并发限流（默认 50 个在飞请求，超出返回 `429 too_many_concurrent_requests`，由 `LLM_GATEWAY_MAX_CONCURRENT_PER_TEAM` 配置）
- 运维观测
  - 请求日志（request logs，30 天自动清理）
  - 用量台账（day/week/month 三粒度）
  - Filament 后台统计看板
  - Prometheus Metrics 端点（`GET /api/metrics`）
  - Health Check 端点（`GET /api/health`，检查 database + cache）
  - 结构化 JSON 日志通道（兼容 ELK / Loki 等日志聚合系统）
- 商业化能力
  - **预付费钱包**：Stripe Checkout 充值 → 实时扣费（成本 + markup 利润）
  - **后付费订阅**：Stripe Subscription 联动配额，月度出账
  - **信用额度（Credit Limit）**：后付费钱包可配置 `credit_limit_cents` 最大负余额上限，防止欠款失控
  - **Stripe Customer Portal**：客户自服务管理订阅、支付方式与历史发票（`POST /{team}/billing/portal`）
  - 月度按用量出账（invoice + items），自动跳过预付费团队避免重复计费
  - Stripe Webhook 自动核销（支付、退款、订阅状态同步）
  - 退款回写账单状态（全额 → void，部分 → 备注记录）
  - 逾期账单自动标记 + 邮件通知 team owner
  - 钱包余额不足主动告警（邮件通知 + 客户事件 Webhook）
  - 新用户注册自动赠送额度（默认 $5.00，可配置）
  - 账单 PDF 生成（可打印 HTML 发票页面，支持浏览器导出 PDF）
- 客户事件 Webhook（平台 → 客户）
  - 事件推送：配额超限、余额不足、账单逾期等
  - HMAC-SHA256 签名验证
  - 事件订阅过滤 + 连续 10 次失败自动禁用
  - 失败投递自动重试（指数退避：30s / 2m / 10m / 30m / 1h，最多 5 次）
  - 完整投递日志（请求/响应/状态码/延迟/错误/重试次数/下次重试时间）
- 用户前台
  - **Landing Page**：产品介绍首页（Hero / Features / Pricing / CTA），暗色主题，动态渐变视觉
  - **API 文档页面**：完整 API Reference（端点、参数、示例、错误码），`/docs`
  - **Dashboard**：实时用量看板（今日/本月 token、请求数、错误率、Top Models、14 天趋势图）
  - **API Keys 管理**：创建、查看、轮换（Rotate）、删除 API Key，支持模型白名单、IP 白名单与日限额配置
  - **Playground**：内置 API 调试页面，支持模型选择、System Prompt、参数配置、响应展示与 token 用量统计
  - **Usage 用量页**：按日/周/月三粒度查看团队 token 消耗与费用明细
  - **Request Logs**：API 请求日志查询，支持按状态码、模型筛选与分页
  - **Billing 账单与订阅**：查看计划、Stripe Checkout 订阅升级、钱包充值（Top Up）、Stripe Customer Portal 跳转、交易流水、账单 PDF 下载
  - **数据导出**：Usage / Wallet Transactions / Invoices 支持 CSV 导出
  - **团队邀请**：邮件邀请 + 站内接受邀请流程
  - **个人设置（Settings）**：资料编辑（Profile）、外观偏好（Appearance）、安全设置（Security：2FA / Passkey / 密码 / 注销账号）、团队管理（Teams：创建、编辑、成员、邀请）
  - **Passkey 登录**：基于 WebAuthn 的无密码登录（通过 `laravel/passkeys`，登录/注册响应自动跳转当前团队）
  - **静态信息页**：`/terms` 服务条款、`/privacy` 隐私政策

## 技术栈

- PHP 8.5
- Laravel 13
- Filament 5
- Livewire 4
- Flux UI 2（前台组件库）
- Fortify（认证后端：登录 / 注册 / 2FA / 密码重置 / 邮箱验证）
- Laravel Passkeys（WebAuthn 无密码登录）
- Pest 4
- Tailwind CSS v4

## 项目结构（简要）

- `app/Actions/Gateway`: 网关处理、协议转换、重试熔断、幂等、Provider 密钥解析、内容过滤
- `app/Actions/Usage`: 用量记账、配额校验（token + 消费金额）、阈值告警
- `app/Actions/Billing`: 钱包扣费/充值、出账、支付、订阅配额同步、定价
- `app/Actions/ApiKeys`: API Key 生成、轮换
- `app/Actions/Audit`: 审计日志记录
- `app/Actions/Teams`: 团队创建、所有权转移
- `app/Actions/Webhooks`: 客户事件 Webhook 分发
- `app/Http/Middleware`: API Key 鉴权、限流（含 Rate Limit 响应头）、IP 白名单校验、团队并发请求限制
- `app/Http/Controllers/Gateway`: 兼容网关控制器
- `app/Http/Controllers/Billing`: Stripe Webhook、钱包充值、Stripe Customer Portal
- `app/Http/Controllers`: Health Check、Prometheus Metrics、数据导出、账单视图
- `app/Http/Responses`: Fortify 登录/注册/2FA/邮箱验证/Passkey 响应（自动跳转当前团队）
- `app/Console/Commands`: 月度出账（队列化）、逾期标记+通知、钱包余额检查、Provider 健康检查、日志清理、异常检测
- `app/Jobs`: 月度账单生成队列 Job、Webhook 投递重试 Job
- `app/Filament`: 管理后台资源与运营看板（含审计日志查看、MCP Servers 配置、Profit Dashboard）
- `app/Notifications/Teams`: 配额告警、异常检测、团队邀请、余额不足、账单逾期通知
- `app/Models`: ApiKey、AuditLog、BillingInvoice(Item)、LlmModel/Provider、McpServer、Team（含 Wallet/Subscription/QuotaPolicy/Entitlements/WebhookEndpoint）、User 等
- `database/migrations`: 网关、用量、配额、账单、钱包、审计日志、Webhook 端点、Webhook 投递日志、Passkeys 等表结构
- `resources/views/pages/`: Livewire 匿名页面组件（Dashboard、API Keys、Playground、Usage、Billing、Request Logs、Settings、Teams）
- `resources/views/pages/auth`: Fortify 认证页面（登录、注册、2FA、密码重置、邮箱验证）
- `resources/views/docs.blade.php`: API 文档页面
- `resources/views/invoices/show.blade.php`: 可打印账单页面
- `resources/views/welcome.blade.php`: 产品 Landing Page
- `tests/Feature`: 网关/用量/计费/后台/团队/前台页面/审计日志/Webhook/导出/认证/Passkey 等功能测试（404 个测试用例）

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
- `LLM_GATEWAY_MAX_CONCURRENT_PER_TEAM`（团队级在飞请求上限，默认 50）

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
- `BILLING_SIGNUP_CREDIT_CENTS`（新用户注册赠送额度，默认 500 = $5.00）
- `BILLING_CHECKOUT_SUCCESS_URL`
- `BILLING_CHECKOUT_CANCEL_URL`
- `BILLING_WALLET_RECHARGE_SUCCESS_URL`
- `BILLING_WALLET_RECHARGE_CANCEL_URL`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_WEBHOOK_TOLERANCE_SECONDS`

### 日志

- `LOG_CHANNEL`（设为 `json` 启用结构化 JSON 日志）
- `LOG_STACK`（设为 `json` 在 stack 中包含 JSON 日志）
- `LOG_JSON_INCLUDE_STACKTRACES`（JSON 日志是否包含堆栈跟踪）

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

### Embeddings

```bash
curl -X POST http://127.0.0.1:8000/api/v1/embeddings \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "text-embedding-3-small",
    "input": "The quick brown fox"
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

### 列出可用模型

```bash
curl http://127.0.0.1:8000/api/v1/models \
  -H "Authorization: Bearer <API_KEY>"
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

### Stripe Customer Portal（客户自服务）

```bash
curl -X POST http://127.0.0.1:8000/{team_slug}/billing/portal \
  -H "Authorization: Bearer <SESSION_COOKIE>"
```

返回 Stripe Customer Portal URL，客户可在 Stripe 托管页面管理订阅、支付方式与历史发票。要求调用者拥有 `ManageBilling` 团队权限。

## 运维端点

### Health Check

```bash
curl http://127.0.0.1:8000/api/health
```

返回 `database` 和 `cache` 子系统状态及延迟，HTTP 200 表示健康，503 表示降级。

### Prometheus Metrics

```bash
curl http://127.0.0.1:8000/api/metrics
```

输出 Prometheus 兼容格式的指标，包括请求总数、错误数、token 消耗、团队/API Key 数量、Provider 健康状态、钱包余额、账单状态、平均延迟等。

## 运营命令

### 生成月账单

```bash
# 默认只为后付费团队生成（预付费团队已通过钱包实时扣费）
# 每个团队的账单生成会 dispatch 到队列异步执行
php artisan billing:generate-monthly-invoices --month=2026-06

# 包含预付费团队（仅作对账用途）
php artisan billing:generate-monthly-invoices --month=2026-06 --include-prepaid

# 按团队生成
php artisan billing:generate-monthly-invoices --month=2026-06 --team-id=1
```

### 标记逾期账单（含邮件通知）

```bash
php artisan billing:mark-overdue-invoices
```

标记逾期账单后，自动向 team owner 发送邮件通知，同时触发 `invoice.overdue` 客户事件 Webhook。

### 检查钱包余额（含邮件通知）

```bash
php artisan billing:check-wallet-balances --threshold=500
```

检查预付费团队钱包余额，低于阈值时向 team owner 发送邮件通知（每日去重），同时触发 `wallet.balance_low` 客户事件 Webhook。

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

- 每月 1 日 01:10 自动执行上一月出账（跳过预付费团队，队列化执行）
- 每小时执行逾期账单扫描 + 通知
- 每小时执行钱包余额检查 + 通知
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

## 客户事件 Webhook（平台 → 客户）

团队可在管理后台配置 Webhook 端点，接收平台推送的事件通知。

### 支持的事件

- `quota.threshold_exceeded`：配额阈值超限
- `wallet.balance_low`：钱包余额不足
- `invoice.overdue`：账单逾期

### 签名验证

每个请求携带 `X-Webhook-Signature` 头，格式为 `sha256=<HMAC>`，使用端点密钥对 body 进行 HMAC-SHA256 签名。

### 投递日志与重试

所有投递记录（含请求 payload、响应状态码、响应体、延迟、错误信息、重试次数、下次重试时间）存储在 `webhook_deliveries` 表，可在后台查看。连续 10 次失败的端点会被自动禁用。

失败的投递由 `RetryWebhookDeliveryJob` 异步重试，采用指数退避：30s → 2m → 10m → 30m → 1h，最多 5 次尝试。

## 计费模型

### 预付费（推荐）

1. 客户在 dashboard 发起充值 → Stripe Checkout 支付
2. Webhook 回调自动入账团队钱包（幂等）
3. 网关每次请求实时扣费（成本 + markup 利润）
4. 余额不足时预检拦截，返回 HTTP 402
5. 余额低于阈值时自动邮件通知 + Webhook 推送

### 后付费

1. 客户订阅 Stripe 计划 → Webhook 同步订阅状态
2. 订阅激活时自动创建后付费钱包（允许余额为负）
3. 网关每次请求实时扣费（余额变负代表欠款）
4. 月底自动生成发票 → Stripe Checkout 收款

### 信用额度（Credit Limit）

后付费钱包可配置 `credit_limit_cents`，限制最大负余额（单位：分）。当负余额达到上限时网关预检拦截，防止欠款失控。建议生产环境为每个后付费团队显式设置该值。

### 新用户赠送额度

注册时自动为新团队钱包充值赠送额度（默认 $5.00，通过 `BILLING_SIGNUP_CREDIT_CENTS` 配置），让客户立即体验产品。

### 定价配置

每个 `LlmModel` 支持三种定价模式（优先级递减）：

1. `sell_input_per_1m_usd` / `sell_output_per_1m_usd`：直接设定售价
2. `markup_percent` + `cost_input_per_1m_usd` / `cost_output_per_1m_usd`：成本加成
3. `pricing` JSON（legacy）：兼容旧格式

### 模型降级（Model Fallback）

`LlmModel` 可配置 `fallback_model_id`，当主模型因熔断或上游 5xx 不可用时，网关会自动重试到降级模型，提升整体可用性。

### 消费上限

`TeamQuotaPolicy` 除 token 限额外，支持 `daily_spend_limit_cents` 按金额的日消费封顶，防止高价模型异常用量导致账单爆炸。

## 数据导出

支持 CSV 格式导出以下数据，用于财务对账与用量分析：

- **Usage 导出**：`GET /{team}/usage/export`
- **钱包交易流水导出**：`GET /{team}/billing/transactions/export`
- **账单导出**：`GET /{team}/billing/invoices/export`

## 测试

运行全量测试：

```bash
php artisan test --compact
```

运行核心平台回归：

```bash
php artisan test --compact tests/Feature/Gateway tests/Feature/Usage tests/Feature/ApiKeys tests/Feature/Billing tests/Feature/LlmPlatformAdminSetupTest.php
```

运行前台页面测试：

```bash
php artisan test --compact tests/Feature/DashboardPageTest.php tests/Feature/BillingPageTest.php tests/Feature/RequestLogsPageTest.php tests/Feature/ApiKeyRotationTest.php
```

## 管理后台

使用 Filament 进行运营管理，包含以下资源与页面：

- **Gateway Configuration**：Llm Providers、Llm Models（含 `fallback_model_id` 降级配置）、MCP Servers、Team Provider/Model Entitlements
- **Operations**：Profit Dashboard（跨团队财务总览：总收入、按模型/按团队拆分）
- **Usage**：Usage Overview 看板、Request Logs、API Keys
- **Billing**：Billing Invoices
- **Governance**：Team Quota Policies、Audit Logs

在本地启动后访问：

- `/admin`

## 生产建议

- 配置 Redis 作为缓存与限流存储
- 开启队列 Worker（`php artisan queue:work`）处理月度出账、Webhook 投递等异步任务
- 为 webhook 增加公网 TLS 与源地址策略
- 将所有定时命令纳入 Laravel scheduler
- 使用 `LOG_CHANNEL=json` 启用结构化 JSON 日志，便于 ELK / Loki 聚合分析
- 通过 `GET /api/metrics` 接入 Prometheus / Grafana 监控告警
- 通过 `GET /api/health` 配置负载均衡器 / K8s 健康探针
- 对 `X-Trace-Id` 做日志与 APM 关联
- 监控 `gateway.wallet.debit_failed` 日志，及时发现余额异常
- 定期审计 `team_wallet_transactions` 流水与 `webhook_deliveries` 投递日志

## License

Proprietary (internal/commercial use by project owner).

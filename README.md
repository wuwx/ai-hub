# AI Hub

AI Hub 是一个基于 Laravel 13 + Filament 5 + Livewire 4 构建的 LLM API 网关。

它提供统一的 OpenAI / Anthropic 兼容网关、API Key 鉴权与配额、基于订阅计划的模型准入控制与定价能力，目标是让团队快速接入并统一调度多家大模型供应商，而无需关心各家协议差异。

> 注意：当前版本**不持久化任何请求日志或按请求的用量明细**（无 request logs、无 usage ledger）。配额通过订阅计划的开关特性（toggle features）实时校验，指标仅通过 Prometheus 暴露聚合计数。

## 核心能力

- 统一兼容网关
  - OpenAI: `/api/v1/chat/completions`, `/api/v1/responses`, `/api/v1/embeddings`
  - Anthropic: `/api/v1/messages`
  - 流式（SSE）与非流式双通道，流式响应携带最终 usage 信息
- 鉴权与账户
  - API Key 鉴权（`Authorization: Bearer`，基于 Sanctum Personal Access Token）
  - Fortify：登录 / 注册 / 2FA / 密码重置 / 邮箱验证
  - Laravel Passkeys（WebAuthn 无密码登录）
  - 审计日志（spatie/laravel-activitylog）：记录 API Key 创建/吊销/轮换等关键操作
- 订阅与配额
  - 基于 Subscriptionify 的订阅计划（Plan）控制模型 / 供应商准入（开关特性：`model:<id>` / `provider:<slug>`）
  - Token 配额（日 / 周 / 月），由订阅计划定义，并通过计费页（`pages::billing` 的 `subscribeToPlan` 动作）直接在用户上开通 / 切换计划
- 模型定价
  - `App\Actions\Billing\ResolveModelPricing` 解析售价，每个 `LlmModel` 支持三种模式（优先级递减）：
    1. `sell_input_per_1m_usd` / `sell_output_per_1m_usd`：直接设定售价
    2. `markup_percent` + `cost_input_per_1m_usd` / `cost_output_per_1m_usd`：成本加成
    3. `pricing` JSON（legacy）：兼容旧格式
- 可靠性与风控
  - 上游重试（指数退避，同模型 5xx / 超时自动重试，由 `retry_attempts` / `retry_backoff_ms` 配置）
  - 幂等键缓存与冲突保护
  - 标准 Rate Limit 响应头（`X-RateLimit-Limit` / `X-RateLimit-Remaining` / `X-RateLimit-Reset`）
  - 团队级并发限流（默认 50 个在飞请求，超出返回 `429 too_many_concurrent_requests`，由 `LLM_GATEWAY_MAX_CONCURRENT_PER_TEAM` 配置）
- API Key 治理
  - Team 维度 API Key，支持模型白名单、IP 白名单（CIDR）、日 token 限额、每分钟限流
- 运维观测
  - Prometheus Metrics 端点（`GET /api/metrics`）：用户数、API Key 数、Provider 健康状态、订阅数等聚合计数
  - Health Check 端点（`GET /api/health`，检查 database + cache）

## 技术栈

- PHP 8.5
- Laravel 13
- Filament 5
- Livewire 4
- Flux UI 2（前台组件库）
- Fortify（认证后端：登录 / 注册 / 2FA / 密码重置 / 邮箱验证）
- Laravel Passkeys（WebAuthn 无密码登录）
- Subscriptionify（订阅计划与开关特性）
- Pest 4
- Tailwind CSS v4
- spatie/laravel-activitylog（审计日志 / 操作记录）

## 项目结构（简要）

- `app/Actions/Gateway`: 网关处理（鉴权透传、协议识别与错误体适配）、幂等、Provider 密钥解析
- `app/Actions/Billing`: 定价解析、订阅配额同步
- `app/Actions/ApiKeys`: API Key 生成、轮换
- `app/Actions/Audit`: 审计日志记录
- `app/Actions/Fortify`: 认证相关
- `app/Http/Middleware`: API Key 鉴权、限流（含 Rate Limit 响应头）、IP 白名单校验、团队并发请求限制
- `app/Http/Controllers/Gateway`: 兼容网关控制器
- `app/Http/Controllers`: Health Check、Prometheus Metrics
- `app/Http/Responses`: Fortify 登录/注册/2FA/邮箱验证/Passkey 响应（自动跳转当前团队）
- `app/Models`: `User`（同时作为 Subscriptionify 的 subscribable）、`LlmProvider`、`LlmModel`（订阅 / 计划 / 特性由 Subscriptionify 提供 `Plan` / `Subscription` / `Feature` 模型）
- `app/Services`: `PlanService` 等
- `database/migrations`: 网关（llm_providers / llm_models）、订阅（subscriptionify 的 plans / features / subscriptions / feature_*）、活动日志（spatie activity_log）、Passkeys、Fortify 等表结构
- `resources/views/pages/`: Livewire 匿名页面组件（dashboard、api-keys、playground、billing、settings、teams）
- `resources/views/pages/auth`: Fortify 认证页面（登录、注册、2FA、密码重置、邮箱验证）
- `resources/views/docs.blade.php`: API 文档页面
- `resources/views/welcome.blade.php`: 产品 Landing Page
- `tests/Feature`: 网关 / 配额 / 计费 / 后台 / 前台页面 / 审计日志 / 认证 / Passkey 等功能测试

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

输出 Prometheus 兼容格式的指标，包括用户数、API Key 数（含活跃数）、各 Provider 健康状态、订阅数（active / trialing / past_due）等聚合计数。

## 管理后台

使用 Filament 进行运营管理，包含以下资源与页面：

- **Gateway Configuration**：Llm Providers、Llm Models、Plans（含 Model / Provider Entitlements）
- **Governance**：API Keys、Audit Logs

在本地启动后访问：

- `/admin`

## 前台页面

- **Dashboard**：账户与配额概览（当前 Plan、Token 配额使用等）
- **API Keys**：创建、查看、轮换（Rotate）、删除 API Key，支持模型白名单、IP 白名单与日限额配置
- **Playground**：内置 API 调试页面，支持模型选择、System Prompt、参数配置与响应展示
- **Billing**：查看当前订阅计划（基于 Subscriptionify）
- **Settings**：资料编辑（Profile）、外观偏好（Appearance）、安全设置（Security：2FA / Passkey / 密码 / 注销账号）、团队管理（Teams）
- **静态信息页**：`/terms` 服务条款、`/privacy` 隐私政策

## 测试

运行全量测试：

```bash
php artisan test --compact
```

运行核心平台回归：

```bash
php artisan test --compact tests/Feature/Gateway tests/Feature/ApiKeys tests/Feature/Billing tests/Feature/LlmPlatformAdminSetupTest.php
```

运行前台页面测试：

```bash
php artisan test --compact tests/Feature/DashboardPageTest.php tests/Feature/BillingPageTest.php tests/Feature/ApiKeyRotationTest.php
```

## 生产建议

- 配置 Redis 作为缓存与限流存储
- 通过 `GET /api/metrics` 接入 Prometheus / Grafana 监控告警
- 通过 `GET /api/health` 配置负载均衡器 / K8s 健康探针

## License

Proprietary (internal/commercial use by project owner).

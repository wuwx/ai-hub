# AI Hub

AI Hub 是一个基于 Laravel 13 + Filament 5 + Livewire 4 构建的 LLM API 网关与管理平台。

它提供统一的 OpenAI / Anthropic 兼容网关、API Key 鉴权（Sanctum）、基于订阅计划的模型管理与定价能力，目标是让团队快速接入并统一调度多家大模型供应商，而无需关心各家协议差异。

## 核心能力

- **统一兼容网关**
  - OpenAI: `/api/v1/chat/completions`, `/api/v1/responses`, `/api/v1/embeddings`, `/api/v1/models`
  - Anthropic: `/api/v1/messages`
  - 流式（SSE）与非流式双通道透明代理
- **鉴权与账户**
  - API Key 鉴权（`Authorization: Bearer`，基于 Sanctum Personal Access Token）
  - Fortify：登录 / 注册 / 2FA / 密码重置 / 邮箱验证
  - Laravel Passkeys（WebAuthn 无密码登录）
  - 审计日志（spatie/laravel-activitylog）：记录 API Key 创建/吊销/轮换等关键操作
- **订阅与计划**
  - 基于 Subscriptionify 的订阅计划（Plan）管理
  - 通过 Filament 后台管理 Plans、Features
- **可靠性**
  - 上游重试（指数退避，5xx / 超时自动重试，由 `retry_attempts` / `retry_backoff_ms` 配置）
  - API 限流（`throttle:api`，默认 60 次/分钟）
- **运维观测**
  - Prometheus Metrics 端点（`GET /metrics`）：用户数、API Key 数、Provider 健康状态、订阅数等聚合计数
  - Health Check 端点（`GET /health`，检查 database + cache + disk）

## 技术栈

| 层级 | 技术 |
|------|------|
| 语言 | PHP 8.5 |
| 后端框架 | Laravel 13 |
| 管理后台 | Filament 5 |
| 前台交互 | Livewire 4 + Flux UI 2 |
| 认证 | Fortify + Laravel Passkeys（WebAuthn） |
| API 鉴权 | Laravel Sanctum |
| 订阅计费 | Subscriptionify |
| 审计日志 | spatie/laravel-activitylog |
| 健康检查 | spatie/laravel-health |
| 监控指标 | spatie/laravel-prometheus |
| 测试 | Pest 4 |
| 静态分析 | Larastan (level 7) |
| 前端构建 | Vite 8 + Tailwind CSS v4 |

## 项目结构（简要）

```
app/
├── Actions/Fortify/          # 认证相关（CreateNewUser、ResetUserPassword）
├── Concerns/                 # 共享 trait（PasswordValidationRules、ProfileValidationRules）
├── Filament/
│   └── Resources/            # 管理后台资源
│       ├── AuditLogs/        #   审计日志
│       ├── LlmModels/        #   LLM 模型管理
│       ├── LlmProviders/     #   LLM 供应商管理
│       └── Plans/            #   订阅计划管理
├── Http/
│   ├── Controllers/Api/V1/   # 网关控制器（Completions、Embeddings、Messages、Models、Responses）
│   └── Responses/            # Fortify 登录/注册/2FA 等响应
├── Livewire/Actions/         # Livewire 动作（Logout）
├── Models/                   # User、LlmProvider、LlmModel
└── Providers/                # AppService、Fortify、Filament AdminPanel、Prometheus

database/
├── factories/                # UserFactory
├── migrations/               # 用户、网关、订阅、活动日志、Passkeys、Health 等表
└── seeders/                  # DatabaseSeeder、SubscriptionifySeeder

resources/views/
├── pages/                    # Livewire 页面（dashboard、api-keys、playground、billing、settings）
├── pages/auth/               # Fortify 认证页面（登录、注册、2FA、密码重置、邮箱验证）
├── components/               # 共享 Blade 组件
├── layouts/                  # 布局模板
├── docs.blade.php            # API 文档页面
└── welcome.blade.php         # Landing Page

tests/Feature/                # 网关、API Key、计费、审计、认证、Health、Prometheus 等功能测试
```

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

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `LLM_GATEWAY_TIMEOUT_SECONDS` | 120 | 上游请求超时（秒） |
| `LLM_GATEWAY_ANTHROPIC_VERSION` | 2023-06-01 | Anthropic API 版本 |
| `LLM_GATEWAY_RETRY_ATTEMPTS` | 2 | 重试次数 |
| `LLM_GATEWAY_RETRY_BACKOFF_MS` | 150 | 重试退避（毫秒） |
| `LLM_GATEWAY_IDEMPOTENCY_TTL_SECONDS` | 300 | 幂等键 TTL |
| `LLM_GATEWAY_API_KEY_RATE_LIMIT_PER_MINUTE` | 120 | API Key 每分钟限流 |
| `LLM_GATEWAY_API_KEY_RATE_LIMIT_DECAY_SECONDS` | 60 | 限流衰减窗口 |

### 计费

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `BILLING_CURRENCY` | USD | 计费货币 |
| `BILLING_FREE_PLAN_CODE` | free | 免费计划代码 |

### 上游 Provider 密钥

在 Filament 后台创建 Provider 时填写 `secret_ref`（加密存储），支持 `bearer` 和 `header` 两种认证模式。

## API 网关示例

### OpenAI Chat Completions

```bash
curl -X POST http://localhost:8000/api/v1/chat/completions \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4.1",
    "messages": [{"role": "user", "content": "你好"}],
    "stream": false
  }'
```

### OpenAI Responses

```bash
curl -X POST http://localhost:8000/api/v1/responses \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4.1",
    "input": "你好"
  }'
```

### Embeddings

```bash
curl -X POST http://localhost:8000/api/v1/embeddings \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "text-embedding-3-small",
    "input": "The quick brown fox"
  }'
```

### Anthropic Messages

```bash
curl -X POST http://localhost:8000/api/v1/messages \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "claude-3-7-sonnet",
    "messages": [{"role": "user", "content": "hello"}],
    "max_tokens": 128
  }'
```

### 列出可用模型

```bash
curl http://localhost:8000/api/v1/models \
  -H "Authorization: Bearer <API_KEY>"
```

## 运维端点

### Health Check

```bash
curl http://localhost:8000/health
```

返回 `database`、`cache`、`disk` 子系统状态及延迟，HTTP 200 表示健康，503 表示降级。

### Prometheus Metrics

```bash
curl http://localhost:8000/metrics
```

输出 Prometheus 兼容格式的指标（命名空间 `ai_hub`），包括：
- `ai_hub_users`：注册用户总数
- `ai_hub_api_keys` / `ai_hub_api_keys_active`：API Key 总数与活跃数
- `ai_hub_provider_active{provider="slug"}`：各 Provider 可用状态
- `ai_hub_subscriptions{status="active|trialing|past_due"}`：订阅数按状态分组

## 管理后台

使用 Filament 5 进行运营管理，访问路径：`/admin`

| 导航组 | 资源 | 说明 |
|--------|------|------|
| — | LLM Providers | 供应商管理（名称、适配器类型、Base URL、认证模式、密钥） |
| — | LLM Models | 模型管理（关联 Provider、外部模型 ID、能力、定价、上下文窗口） |
| Billing | Plans | 订阅计划管理（Subscriptionify） |
| — | Audit Logs | 操作审计日志（只读） |

## 前台页面

| 路径 | 页面 | 说明 |
|------|------|------|
| `/dashboard` | Dashboard | 账户与订阅概览 |
| `/api-keys` | API Keys | 创建、查看、轮换、删除 API Key |
| `/playground` | Playground | 内置 API 调试，支持模型选择与参数配置 |
| `/billing` | Billing | 查看与管理订阅计划 |
| `/settings/profile` | Profile | 资料编辑 |
| `/settings/appearance` | Appearance | 外观偏好 |
| `/settings/security` | Security | 2FA / Passkey / 密码 / 注销账号 |
| `/docs` | API Docs | API 文档 |
| `/terms` | Terms | 服务条款 |
| `/privacy` | Privacy | 隐私政策 |

## 测试

运行全量测试：

```bash
php artisan test --compact
```

运行网关相关测试：

```bash
php artisan test --compact tests/Feature/Gateway
```

运行完整质量检查（lint + 静态分析 + 测试）：

```bash
composer test
```

## 生产建议

- 配置 Redis 作为缓存与限流存储
- 通过 `GET /metrics` 接入 Prometheus / Grafana 监控告警
- 通过 `GET /health` 配置负载均衡器 / K8s 健康探针
- 生产环境设置 `APP_ENV=production`，自动启用强密码策略与破坏性命令保护

## License

Proprietary (internal/commercial use by project owner).

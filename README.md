<div align="center">

# 言匣 QuoteBox

**把好句子装进一只匣子，随时随机取出一句。**

面向虚拟主机的轻量随机一言服务 · 纯 PHP + MySQL · 零依赖 · 上传即用

[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL 5.7+ / MariaDB](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-5.7%2B%20%2F%2010.2%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-0-3D6B4F)](#requirements)
[![No Build](https://img.shields.io/badge/build-none-A6402D)](#development)
[![Version](https://img.shields.io/badge/version-1.0.0-26221C)](#)

**作者：** [Mitchll1214](https://github.com/Mitchll1214) · **仓库：** [Mitchll1214/quotebox](https://github.com/Mitchll1214/quotebox)

[功能](#features) · [快速开始](#quick-start) · [API](#api) · [管理后台](#admin) · [常见问题](#faq)

</div>

---

<a id="about"></a>

## 项目简介

言匣（QuoteBox）是一个为**虚拟主机**准备的随机一言服务。

它不依赖 Composer，不需要 Node.js 或构建工具，也不用配置伪静态。把文件上传到网站目录，创建好数据库，访问一次安装向导，就能得到：

- 一个可以直接调用的随机一言 API；
- 一个可以维护句子与分类的管理后台；
- 一个自带实时演示与接口说明的站点首页。

句子支持作者、出处、分类与启用状态；分类拥有独立的 API 参数（slug），因此可以按「诗词」「电影」「语录」「代码」等维度随机取句。所有内容与分类都在后台维护，不需要再改代码。

> 适合个人博客、导航站、静态页面、班级/社团站点，以及任何可以发起 HTTP 请求的地方。

<a id="features"></a>

## 核心功能

| 能力 | 说明 |
| --- | --- |
| **随机一言 API** | 一次请求随机返回一条启用中的句子，支持 JSON、纯文本与 JSONP |
| **分类过滤** | 使用后台维护的分类参数调用，例如 `?c=poem`；支持逗号分隔多个分类 |
| **首次安装向导** | 自动检测环境、创建数据表、创建管理员、写入示例数据与配置文件 |
| **文料管理** | 新增、编辑、删除、搜索、按分类/状态筛选、分页浏览 |
| **批量导入** | 粘贴文本（一行一条）或上传 Excel / CSV；自动跳过表头，CSV 支持 UTF-8 / GBK |
| **批量操作** | 批量启用、停用、删除；支持导出 CSV 备份，Excel 可直接打开 |
| **分类维护** | 自定义名称、slug、说明、排序与启用状态；删除分类时文料自动转入未分类 |
| **站点设置** | 修改站点名称、API 跨域来源、管理员密码 |
| **零依赖部署** | 纯 PHP + PDO MySQL，不使用框架、Composer、CDN 或外部服务 |
| **子目录友好** | 自动推断部署路径，放在站点根目录或 `/quotebox/` 等子目录均可 |
| **基础安全** | PDO 预处理、CSRF Token、密码哈希、登录限流、配置目录保护、安装锁定 |

### 设计取舍

- **面向共享主机**：所有功能都可以通过浏览器完成，不要求 SSH、CLI 或定时任务。
- **数据库保持简单**：只使用四张表，便于迁移、备份和手动维护。
- **随机逻辑可扩展**：先统计可用数量，再随机偏移取一条，避免在大表上使用 `ORDER BY RAND()`。
- **不做过度抽象**：没有模板引擎、路由框架和依赖注入，方便在虚拟主机上直接调试。

## 目录

- [项目简介](#about)
- [核心功能](#features)
- [环境要求](#requirements)
- [快速开始](#quick-start)
- [工作原理](#architecture)
- [目录结构](#structure)
- [API 文档](#api)
- [管理后台](#admin)
- [数据结构](#database)
- [安全设计](#security)
- [部署与运维](#deployment)
- [常见问题](#faq)
- [本地开发](#development)
- [参与贡献](#contributing)
- [版权与许可](#license)

<a id="requirements"></a>

## 环境要求

| 项目 | 最低要求 | 推荐 |
| --- | --- | --- |
| PHP | 7.4 | 8.1 及以上 |
| 数据库 | MySQL 5.7 / MariaDB 10.2 | MySQL 8.0 / MariaDB 10.6+ |
| PHP 扩展 | `pdo_mysql`、`session`、`json` | 额外启用 `mbstring`；导入 `.xlsx` 需要 `zip`（ZipArchive）与 `SimpleXML` |
| Web 服务器 | Apache 2.4 或 Nginx | Apache（仓库已附带 `.htaccess`） |
| HTTPS | 可选 | 建议启用 |

需要确认的 PHP 扩展：

```bash
php -m | grep -E 'pdo_mysql|session|json|mbstring|zip|SimpleXML'
```

不需要 Redis、Memcached、Node.js、Composer 或任何队列服务。`mbstring` 未启用时程序会自动使用兼容回退；`zip` / `SimpleXML` 仅用于 `.xlsx` 导入，缺少时可改用 CSV。

<a id="quick-start"></a>

## 快速开始

### 1. 获取文件

```bash
git clone https://github.com/Mitchll1214/quotebox.git
```

也可以直接下载 ZIP，解压后将里面的文件上传到网站目录。

### 2. 创建数据库

在虚拟主机控制面板中创建一个 MySQL 数据库，并记录：

- 数据库主机（不一定是 `localhost`）
- 端口（通常是 `3306`）
- 数据库名
- 用户名
- 密码

建议字符集选择 `utf8mb4`。安装程序会自动创建数据表，无需手动导入 SQL。

### 3. 上传文件

将项目文件上传到网站根目录，或者任意子目录，例如：

```text
public_html/                 ← 站点根目录
└── quotebox/                ← 也可以放在子目录
    ├── index.php
    ├── api.php
    ├── install.php
    ├── admin/
    ├── app/
    ├── assets/
    ├── config/
    └── ...
```

> 仓库中不包含 `config/config.php`。该文件由安装向导生成，包含数据库凭据，请不要从本地环境一并上传。

### 4. 运行安装向导

在浏览器中打开：

```text
https://你的域名/install.php
```

如果部署在子目录：

```text
https://你的域名/quotebox/install.php
```

安装页会依次检查：

- PHP 版本是否不低于 7.4
- `pdo_mysql`、`session`、`json` 是否可用
- `config` 目录是否可写

然后填写站点名称、数据库信息和管理员账号。点击「开始安装」后，程序会自动：

1. 创建 `qb_categories`、`qb_quotes`、`qb_admins`、`qb_settings` 四张表；
2. 创建管理员账号；
3. 写入 5 个示例分类与 16 条示例文料；
4. 生成 `config/config.php`；
5. 跳转到后台登录页。

### 5. 登录后台

```text
https://你的域名/admin/
```

使用安装时设置的管理员账号登录，然后就可以开始维护句子和分类。

### 6. 安装完成检查

| 检查项 | 操作 |
| --- | --- |
| 删除安装入口 | 安装完成后建议删除服务器上的 `install.php` |
| 访问首页 | 打开 `https://你的域名/`，确认随机一言演示正常 |
| 调用接口 | 打开 `https://你的域名/api.php`，确认返回 JSON |
| 保护配置 | 确认 `config/config.php` 无法被直接访问 |
| 开启 HTTPS | 在主机面板申请并强制 HTTPS |
| 备份 | 保存数据库与 `config/config.php` 的备份 |

安装完成后，只要 `config/config.php` 存在，安装程序就会自动锁定。再次访问 `install.php` 只会显示「已安装」。

<a id="architecture"></a>

## 工作原理

```text
访客 / 前端脚本 ──── GET /api.php?c=poem ────▶ api.php ──── PDO ────▶ MySQL
                                                             ▲
管理员 ───────────── HTTPS /admin/ ──────────▶ 管理后台 ──────┘
```

请求流程：

1. 所有入口先加载 `app/bootstrap.php`，读取 `config/config.php`；
2. API 校验参数，查询启用中的分类与文料；
3. 先统计可用文料数量，再随机生成偏移量，取出其中一条；
4. 按请求格式输出 JSON、纯文本或 JSONP；
5. 后台通过 Session 保持登录，所有写操作都校验 CSRF Token。

首页的「换一句」演示同样调用公开 API，不包含任何只有后台才能访问的数据逻辑。

<a id="structure"></a>

## 目录结构

```text
quotebox/
├── index.php                  # 站点首页：实时随机一言演示 + API 文档
├── api.php                    # 随机一言 API 入口
├── install.php                # 首次安装向导（安装后可删除）
├── .htaccess                  # Apache：目录索引关闭、敏感文件与隐藏目录拦截
├── .gitignore                 # 忽略生成配置、日志与备份
│
├── config/
│   ├── .htaccess              # 禁止直接访问配置目录
│   ├── config.sample.php      # 配置样例，供手动配置参考
│   └── config.php             # 安装时生成，包含数据库凭据（不提交）
│
├── app/
│   ├── bootstrap.php          # 配置加载、错误处理、数据库连接
│   ├── helpers.php            # 工具函数、Session、CSRF、设置缓存、分页
│   ├── Database.php           # PDO 封装与安全查询
│   ├── Spreadsheet.php        # 文本 / CSV / XLSX 导入解析
│   ├── Auth.php               # 管理员登录、退出、登录限流
│   ├── schema.php             # 建表语句定义
│   └── .htaccess              # 禁止直接访问应用目录
│
├── admin/
│   ├── _init.php              # 后台初始化与会话启动
│   ├── _header.php            # 后台公共头部与侧栏
│   ├── _footer.php            # 后台公共底部
│   ├── index.php              # 仪表盘
│   ├── quotes.php             # 文料管理、筛选、批量操作、批量导入
│   ├── categories.php         # 分类管理
│   ├── settings.php           # 站点设置、CORS、修改密码
│   ├── login.php              # 登录
│   ├── logout.php             # 退出
│   └── assets/
│       ├── admin.css          # 后台样式
│       └── admin.js           # 侧栏、批量选择、复制、确认弹窗
│
└── assets/
    ├── style.css              # 首页样式
    ├── install.css            # 安装向导样式
    ├── favicon.svg            # 站点图标（矢量，现代浏览器）
    ├── favicon.ico            # 站点图标（16/32/48 兼容）
    └── apple-touch-icon.png   # iOS 添加到主屏幕图标
```

<a id="api"></a>

## API 文档

### 接口地址

```text
https://你的域名/api.php
```

部署在子目录时：

```text
https://你的域名/quotebox/api.php
```

接口无需登录、无需 API Key。默认允许跨域，可在后台修改允许来源。

### 请求方法

| 方法 | 用途 |
| --- | --- |
| `GET` | 获取随机一言 |
| `HEAD` | 仅获取响应头 |
| `OPTIONS` | CORS 预检 |

其他方法返回 `405 Method Not Allowed`。

### 请求参数

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `c` | string | 否 | 分类参数（slug），多个分类用英文逗号分隔，最多 10 个 |
| `category` | string | 否 | `c` 的别名 |
| `format` | string | 否 | `json`（默认）或 `text`；`plain`、`txt` 等同于 `text` |
| `encode` | string | 否 | `format` 的别名 |
| `callback` | string | 否 | JSONP 回调函数名，提供后优先返回 JavaScript |

> `c` 参数只接受字母、数字、下划线与短横线。分类参数由后台维护，停用的分类不会被返回。

### 示例

#### 随机 JSON

```bash
curl "https://你的域名/api.php"
```

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "id": 1,
    "content": "人生若只如初见，何事秋风悲画扇。",
    "author": "纳兰性德",
    "source": "木兰花·拟古决绝词柬友",
    "category": {
      "name": "诗词",
      "slug": "poem"
    },
    "created_at": "2026-01-01 00:00:00"
  }
}
```

#### 纯文本

```bash
curl "https://你的域名/api.php?format=text"
```

响应体只包含句子正文：

```text
人生若只如初见，何事秋风悲画扇。
```

#### 指定分类

```bash
curl "https://你的域名/api.php?c=poem"
```

#### 多个分类

```bash
curl "https://你的域名/api.php?c=poem,movie"
```

#### JSONP

```html
<script>
function showQuote(payload) {
  document.getElementById('quote').textContent = payload.data.content;
}
</script>
<script src="https://你的域名/api.php?callback=showQuote"></script>
```

响应：

```javascript
/**/ showQuote({"code":200,"message":"success","data":{...}});
```

#### 前端 fetch

```javascript
async function randomQuote(category) {
  const params = new URLSearchParams();
  if (category) params.set('c', category);

  const response = await fetch(`https://你的域名/api.php?${params}`);
  const body = await response.json();

  if (!response.ok || body.code !== 200) {
    throw new Error(body.message || '获取失败');
  }

  return body.data;
}

randomQuote('poem').then((quote) => {
  console.log(quote.content, quote.author);
});
```

### 响应字段

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `code` | int | 业务状态码，成功为 `200` |
| `message` | string | 状态说明 |
| `data.id` | int | 文料 ID |
| `data.content` | string | 句子正文 |
| `data.author` | string | 作者，可能为空字符串 |
| `data.source` | string | 出处，可能为空字符串 |
| `data.category` | object \| null | `{ name, slug }`；文料未分类时为 `null` |
| `data.created_at` | string | 添加时间，格式 `Y-m-d H:i:s` |

### 错误响应

JSON 模式返回带错误字段的对象：

```json
{
  "code": 404,
  "message": "分类不存在或已停用：unknown",
  "error": "category_not_found"
}
```

`format=text` 模式下，错误信息以纯文本返回，HTTP 状态码保持不变。

| HTTP 状态码 | `error` 示例 | 场景 |
| --- | --- | --- |
| `400` | `invalid_format` / `invalid_category` / `too_many_categories` | 参数格式不正确 |
| `404` | `category_not_found` / `quote_not_found` | 分类不存在、已停用，或该范围内没有可用句子 |
| `405` | `method_not_allowed` | 使用了 GET/HEAD/OPTIONS 之外的方法 |
| `500` | `server_error` | 数据库或服务器内部错误 |
| `503` | `not_installed` | 尚未完成安装 |

### 行为说明

- 只返回 `is_active = 1` 的文料；
- 只返回启用分类下的文料，以及未分类文料；
- 停用分类后，该分类下的文料不会出现在 API 中；
- 响应默认带 `Cache-Control: no-store`，避免随机结果被缓存；
- CORS 默认 `Access-Control-Allow-Origin: *`，可在后台「设置」中改成来源白名单；
- 没有内置 API 限流。公开站点流量较大时，建议在主机面板、CDN 或 WAF 侧配置限流。

<a id="admin"></a>

## 管理后台

### 入口

```text
https://你的域名/admin/
```

安装完成后使用安装向导中设置的用户名和密码登录。

### 页面与功能

| 页面 | 路径 | 功能 |
| --- | --- | --- |
| 概览 | `/admin/index.php` | 查看文料与分类统计、最近添加、API 地址与分类参数 |
| 文料库 | `/admin/quotes.php` | 新增/编辑/删除文料，搜索、筛选、分页、批量操作、批量导入、导出 CSV |
| 分类 | `/admin/categories.php` | 新建/编辑/删除分类，维护 slug、说明、排序与状态 |
| 设置 | `/admin/settings.php` | 站点名称、API 跨域来源、修改密码、系统信息 |

### 文料字段

| 字段 | 必填 | 限制 | 说明 |
| --- | --- | --- | --- |
| 内容 | 是 | 最多 2000 字符 | 句子正文 |
| 作者 | 否 | 最多 100 字符 | 留空时列表显示「佚名」 |
| 出处 | 否 | 最多 200 字符 | 书名、电影名、演讲等 |
| 分类 | 否 | 必须存在 | 不选则为「未分类」 |
| 状态 | 否 | 启用/停用 | 停用的文料不会被 API 返回 |

### 批量导入与导出

在「文料库 → 批量导入」中可以选择两种方式：粘贴文本，或上传 Excel / CSV。两种方式都需要先选择目标分类（可选「未分类」），导入的文料默认启用，单次最多 2000 条。

#### 方式一：粘贴文本

**一行一条**，直接粘贴即可：

```text
人生若只如初见，何事秋风悲画扇。
山有木兮木有枝，心悦君兮君不知。
```

如需同时填写作者和出处，用 `|`（或制表符）分隔：

```text
人生若只如初见，何事秋风悲画扇。|纳兰性德|木兰花·拟古决绝词柬友
山有木兮木有枝，心悦君兮君不知。|佚名|越人歌
种一棵树最好的时间是十年前，其次是现在。|非洲谚语|
```

规则：

- 一行对应一条文料，可以只写内容，也可以补全作者、出处；
- 以 `#` 开头的行会被忽略，可当作注释；
- 首行如果是「内容 | 作者 | 出处」这类表头会自动跳过；
- 支持 `|` 或制表符分隔。

#### 方式二：上传 Excel / CSV

点击「下载 CSV 模板」可以获得标准表头。列顺序：

| A 列 | B 列 | C 列 |
| --- | --- | --- |
| 内容（必填） | 作者 | 出处 |

规则：

- 支持 `.xlsx` 与 `.csv`，只读取第一个工作表；
- 首行是表头时自动跳过；
- CSV 自动识别 UTF-8 / GBK 编码，以及逗号、分号、制表符分隔；
- 文件最大 5 MB，单次最多 2000 条；
- 旧版 `.xls` 请先在 Excel 中另存为 `.xlsx` 或 CSV；
- 选择目标分类后，整批文料会导入到该分类。

> Excel 导入使用 PHP 自带的 `ZipArchive` 与 `SimpleXML`，不依赖 PhpSpreadsheet 或 Composer。如果主机未启用 `zip` 扩展，请改用 CSV 导入。

#### 导出 CSV

「文料库」右上角的「导出 CSV」会导出全部文料，包含：

```text
内容,作者,出处,分类,状态,创建时间
```

文件使用 UTF-8 BOM 编码，可直接用 Excel 打开，也可以作为迁移或备份的基础数据。

### 分类与 API 参数

分类参数（slug）就是 API 的 `?c=` 值：

```text
分类「诗词」 → slug: poem → GET /api.php?c=poem
```

- slug 仅支持字母、数字、下划线、短横线；
- 留空时系统自动生成类似 `cat-a1b2c3` 的参数；
- 修改 slug 后旧参数立即失效，需要同步更新调用方；
- 删除分类不会删除文料，相关文料会移到「未分类」；
- 停用分类后，其下文料不会出现在 API 结果中。

### 站点设置

- **站点名称**：显示在首页标题、后台与页面页脚；
- **API 跨域来源**：默认 `*`，可填逗号分隔的来源，如 `https://blog.example.com,https://www.example.com`；
- **登录密码**：修改后当前会话保持登录，其他设备需要重新登录。

### 登录保护

- 密码使用 `password_hash()` 保存，不存明文；
- 同一会话连续失败 5 次后锁定 10 分钟；
- 登录成功会重新生成 Session ID；
- 所有后台写操作均校验 CSRF Token，缺失或过期返回 `419`。

<a id="database"></a>

## 数据结构

默认表前缀为 `qb_`，安装时可以自定义。四张表：

| 表 | 用途 | 关键字段 |
| --- | --- | --- |
| `qb_categories` | 分类 | `id`、`name`、`slug`（唯一）、`sort_order`、`is_active` |
| `qb_quotes` | 文料 | `id`、`category_id`、`content`、`author`、`source`、`is_active` |
| `qb_admins` | 管理员 | `id`、`username`（唯一）、`password_hash`、`last_login_at` |
| `qb_settings` | 键值配置 | `setting_key`（主键）、`setting_value` |

说明：

- 文料的 `category_id` 可以为 `NULL`，表示未分类；
- 删除分类时先把相关文料置为未分类，再删除分类；
- 所有表使用 `utf8mb4` 字符集；
- 时间字段统一使用 `DATETIME`；
- 修改表前缀只影响新建安装，不会自动迁移已有数据。

### 备份

```bash
mysqldump -u 数据库用户 -p 数据库名 > quotebox-backup-$(date +%F).sql
```

同时备份 `config/config.php`。恢复时将 SQL 导入同一数据库，并保持表前缀一致即可。

<a id="security"></a>

## 安全设计

| 风险 | 处理方式 |
| --- | --- |
| SQL 注入 | 所有查询使用 PDO 预处理；表名和列名来自代码常量 |
| XSS | 所有用户输入在输出时经过 `htmlspecialchars()` |
| CSRF | 后台所有写操作校验 Session 中的 CSRF Token |
| 密码泄露 | 使用 `password_hash()` / `password_verify()`，自动升级算法 |
| 会话固定 | 登录成功后调用 `session_regenerate_id(true)` |
| 暴力破解 | 同一会话失败 5 次锁定 10 分钟 |
| 配置泄露 | `config/config.php` 带入口保护；Apache 通过 `.htaccess` 拒绝直接访问 |
| 安装被重复执行 | 检测到 `config/config.php` 后自动锁定安装向导 |
| 目录浏览 | Apache 关闭索引；敏感文件扩展名被拦截 |
| 点击劫持 | 响应头设置 `X-Frame-Options: SAMEORIGIN` |
| MIME 嗅探 | 响应头设置 `X-Content-Type-Options: nosniff` |

### 已知边界

- 登录限流基于 Session，清理 Cookie 后计数会重置；如需更强的防护，可在反向代理或 WAF 层增加 IP 限流。
- API 没有鉴权与速率限制，适合公开的随机内容接口；如需私有化调用，建议在 Web 服务器层增加白名单或访问密钥。
- 项目不负责申请 HTTPS 证书，请在主机面板或 CDN 中自行开启。
- 没有内置操作审计日志；生产环境建议保留 Web 服务器访问日志。

<a id="deployment"></a>

## 部署与运维

### Apache

仓库根目录、`config/` 与 `app/` 已包含 `.htaccess`。确认主机开启了 `AllowOverride`，至少允许：

- `Options`（关闭目录索引）
- `FileInfo`（文件匹配与字符集）
- `AuthConfig`（拒绝访问）

如果主机不允许 `.htaccess` 中的某些指令，可以删除对应行，但请保留对 `config/` 和 `app/` 的保护。

### Nginx

Nginx 不读取 `.htaccess`，请将以下规则加入站点配置：

```nginx
# 保护配置与应用目录
location ~ ^/(config|app)/ {
    deny all;
    return 404;
}

# 阻止访问隐藏目录与隐藏文件
location ~ /\.(?!well-known) {
    deny all;
}

# 可选：禁止访问安装文件（安装完成后）
location = /install.php {
    return 404;
}
```

### 子目录部署

程序会根据 `DOCUMENT_ROOT` 与实际文件路径自动推断基础目录。放在以下位置都可以直接使用：

```text
https://example.com/
https://example.com/quotebox/
https://example.com/tools/quotebox/
```

如果使用了反向代理或特殊的目录映射，导致静态资源 404，请检查服务器是否正确传递 `SCRIPT_NAME` 与 `DOCUMENT_ROOT`。

### 权限建议

| 路径 | 建议权限 | 说明 |
| --- | --- | --- |
| 项目目录 | `755` | Web 进程可读 |
| `config/` | 安装时 `755` 或 `775` | 安装需要写入 `config.php` |
| `config/config.php` | `640` 或 `644` | 安装完成后仅需可读 |
| 其他 PHP 文件 | `644` | 不需要执行权限 |

### 环境配置

安装向导生成的 `config/config.php` 中包含：

```php
return array(
    'db' => array(
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'quotebox',
        'username' => 'quotebox_user',
        'password' => 'your-password',
        'charset' => 'utf8mb4',
        'prefix' => 'qb_',
    ),
    'app_key' => '随机生成的密钥',
    'timezone' => 'Asia/Shanghai',
    'debug' => false,
    'installed_at' => '安装时间',
    'version' => '1.0.0',
);
```

- `timezone`：影响展示时间，可改为其他 PHP 时区；
- `debug`：排障时可临时改为 `true`，生产环境请保持 `false`；
- `app_key`：预留的实例密钥，请勿公开；
- 修改数据库信息后，重新访问任意页面即可生效。

### 升级

当前版本没有自动化升级程序。升级步骤：

1. 备份数据库与 `config/config.php`；
2. 覆盖上传新版本代码；
3. 保留服务器上的 `config/config.php`；
4. 若新版本包含数据库变更，按发布说明执行迁移；
5. 访问首页与 API 做一次快速检查。

<a id="faq"></a>

## 常见问题

### 安装页提示「config 目录可写」未通过

将 `config` 目录权限临时改为 `755` 或 `775`，确保 Web 进程可以创建 `config/config.php`。安装完成后可以恢复为只读。

### 数据库连接失败

- 核对主机、端口、数据库名、用户名、密码；
- 很多虚拟主机的数据库主机不是 `localhost`，请以面板显示为准；
- 确认数据库已创建，用户拥有建表权限；
- 确认 PHP 已启用 `pdo_mysql`；
- 检查数据库端口是否被主机商修改。

### 安装时提示已存在管理员账号

说明当前表前缀下已有数据。为避免覆盖：

- 换一个表前缀重新安装；或
- 备份后清空对应的 `qb_admins` 等数据表；或
- 直接使用已有管理员账号登录。

### 忘记后台密码

可以在数据库中用 PHP 生成的哈希替换密码：

```bash
php -r "echo password_hash('你的新密码', PASSWORD_DEFAULT), PHP_EOL;"
```

然后把输出写入：

```sql
UPDATE qb_admins
SET password_hash = '上一步生成的哈希'
WHERE username = 'admin';
```

如果主机没有 PHP CLI，可以在本地 PHP 环境生成哈希后再执行 SQL。也可以删除 `config/config.php` 后重新安装，但请注意已有数据表的处理方式。

### Excel 导入失败或提示未启用 ZipArchive

`.xlsx` 导入依赖 PHP 的 `zip`（ZipArchive）与 `SimpleXML` 扩展。若主机未启用：

- 在 Excel 中将文件「另存为 → CSV UTF-8（逗号分隔）」后导入；或
- 在主机控制面板启用 `zip` / `SimpleXML` 扩展。

旧版 `.xls` 不受支持，请先另存为 `.xlsx` 或 CSV。导入文件最大 5 MB，单次最多 2000 条。

### CSV 导入中文乱码

导入器会自动识别 UTF-8 与 GBK 编码。如果仍然乱码，请在 Excel 中重新「另存为 → CSV UTF-8」后上传，或直接使用 `.xlsx` 导入。

### API 返回 404

依次检查：

1. 分类 slug 是否与后台一致；
2. 分类是否处于启用状态；
3. 该分类下是否有启用中的文料；
4. 是否误把 `?c=` 写成了中文分类名称（应使用 slug）。

### 修改了分类参数后，旧链接失效

slug 是 API 的唯一分类标识。修改 slug 后旧参数立即失效，请同步更新调用方，或改回原 slug。

### 中文显示乱码

程序连接数据库时固定使用 `utf8mb4`。如果旧数据库或旧表使用了其他字符集，建议重新建表或手动转换：

```sql
ALTER TABLE qb_quotes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 页面空白或 500

1. 将 `config/config.php` 中的 `'debug' => true` 临时打开；
2. 刷新页面查看具体错误；
3. 查看 PHP 错误日志或主机控制面板日志；
4. 修复后立即改回 `false`。

常见原因包括数据库凭据错误、`config.php` 丢失、PHP 版本低于 7.4。

### 部署到子目录后样式或链接错误

正常情况下程序会自动识别子目录。如果使用反向代理、软链接或特殊目录映射，请确认 `DOCUMENT_ROOT` 与 `SCRIPT_NAME` 正确；必要时把站点直接部署到域名根目录。

### 想重新安装

1. 备份数据；
2. 删除服务器上的 `config/config.php`；
3. 重新访问 `install.php`；
4. 如需保留原数据，请使用相同的表前缀，并确保 `qb_admins` 中没有旧管理员，或先清空该表。

### 可以关闭 API 吗

可以把所有分类与文料停用，API 将返回 404；也可以删除 `api.php`。首页的实时演示会因此显示获取失败。更推荐在 Web 服务器层限制 `/api.php` 的访问来源。

<a id="development"></a>

## 本地开发

### 启动本地服务

准备好 PHP 与 MySQL 后，在项目根目录运行：

```bash
php -S 127.0.0.1:8080 -t .
```

然后打开：

```text
http://127.0.0.1:8080/install.php
```

### 修改界面

- 首页：`index.php` + `assets/style.css`
- 安装向导：`install.php` + `assets/install.css`
- 管理后台：`admin/*.php` + `admin/assets/admin.css`
- 后台交互：`admin/assets/admin.js`
- 站点图标：`assets/favicon.svg`、`assets/favicon.ico`、`assets/apple-touch-icon.png`

项目没有构建步骤，修改后直接刷新页面即可。

### 开发约定

- 保持 PHP 7.4 兼容；
- 不引入 Composer 依赖或外部 CDN；
- 数据库访问统一使用 `Database` 类与占位符；
- 后台写操作必须保留 CSRF 校验；
- 用户输入输出到 HTML 时必须使用 `e()` 转义；
- 新增入口需要加载 `app/bootstrap.php`；
- 修改表结构时同步更新 `app/schema.php` 与本文档。

### 关键文件

| 文件 | 职责 |
| --- | --- |
| `app/bootstrap.php` | 常量、配置、时区、错误处理、数据库连接 |
| `app/helpers.php` | 转义、URL、Session、CSRF、设置、分页、格式化 |
| `app/Database.php` | PDO 连接、占位符展开、查询与写入封装 |
| `app/Spreadsheet.php` | 文本、CSV、XLSX 导入解析 |
| `app/Auth.php` | 管理员会话、登录验证、登出、安全跳转 |
| `app/schema.php` | 四张表的建表 SQL |
| `install.php` | 安装流程与环境检查 |
| `api.php` | 随机取句、分类过滤、格式输出、CORS |

<a id="contributing"></a>

## 参与贡献

欢迎通过 [GitHub Issues](https://github.com/Mitchll1214/quotebox/issues) 反馈问题或提出建议。

提交 Pull Request 前建议：

1. 先开 Issue 描述要解决的问题，避免重复工作；
2. 保持改动聚焦，不混入无关重构；
3. 确认 PHP 7.4 兼容，且没有新增外部依赖；
4. 手动验证安装、API 与后台相关流程；
5. 更新受影响的 README 或注释。

<a id="license"></a>

## 版权与许可

© 2026 [Mitchll1214](https://github.com/Mitchll1214) · QuoteBox 言匣

本项目当前未附带 `LICENSE` 文件，也未指定开源许可证。在作者另行提供许可证之前，默认保留所有权利。如需商用、重新分发或其他授权，请通过 [GitHub](https://github.com/Mitchll1214) 联系作者。

第三方依赖、字体与外部服务的授权请以各自项目说明为准。项目内使用的示例句子仅用于功能演示，版权归原作者或权利人所有。

---

<div align="center">

**[言匣 QuoteBox](https://github.com/Mitchll1214/quotebox)** · 把好句子装进一只匣子

© 2026 [Mitchll1214](https://github.com/Mitchll1214) · 保留所有权利

</div>

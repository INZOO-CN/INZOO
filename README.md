# VPN客户端&管理系统 | VPN Client & Management System

**重要提示**：本程序与腾讯云无关。本系统仅利用腾讯云提供的基础设施服务和API接口，由映筑视觉INZOO独立开发。

**Important Notice**：This program is not affiliated with Tencent Cloud. This system solely utilizes the infrastructure services and API interfaces provided by Tencent Cloud and is independently developed by INZOO Visual Design.

## 一、环境要求 | Environment Requirements

在安装和运行此VPN客户端&管理系统之前，请确保你的服务器满足以下环境要求：

### 中文版本
- **Web 服务器**：Apache 2.4 或 Nginx 1.14 及以上版本
- **PHP 版本**：PHP 8.1 或更高版本
- **数据库**：MySQL 5.6 或更高版本
- **腾讯云账号**：腾讯云账号及相应的 API 密钥

### English Version
- **Web Server**：Apache 2.4 or Nginx 1.14 and above
- **PHP Version**：PHP 8.1 or higher
- **Database**：MySQL 5.6 or higher
- **Tencent Cloud Account**：Tencent Cloud account and corresponding API keys

## 二、安装步骤 | Installation Steps

### 1. 下载项目代码 | Download Project Code
从 GitHub 仓库克隆项目代码到你的服务器：
```bash
git clone <https://github.com/INZOO-CN/INZOO/>
```

### 2. 配置数据库 | Configure Database
打开 `config.php` 文件，配置数据库连接信息：
```php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASSWORD', 'your_database_password');
```
请将 `your_database_name`、`your_database_user` 和 `your_database_password` 替换为你自己的数据库名称、用户名和密码。

### 3. 配置腾讯云 API | Configure Tencent Cloud API
同样在 `config.php` 文件中，配置腾讯云 API 信息：
```php
// 腾讯云API配置
define('SECRET_ID', 'your_secret_id');
define('SECRET_KEY', 'your_secret_key');
define('REGION', 'your_region');
define('INSTANCE_ID', 'your_instance_id'); // VPN服务器实例ID
```
请将 `your_secret_id`、`your_secret_key`、`your_region` 和 `your_instance_id` 替换为你自己的腾讯云 API 密钥、区域和 VPN 服务器实例 ID。

### 4. 创建数据库表 | Create Database Tables
使用 MySQL 客户端连接到你的数据库，并执行以下 SQL 语句创建所需的表：
```sql
-- 创建用户表
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(255) NOT NULL UNIQUE,
password_hash VARCHAR(255) NOT NULL,
is_active TINYINT(1) DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建VPN连接记录表
CREATE TABLE vpn_connections (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
connection_time DATETIME NOT NULL,
disconnection_time DATETIME,
ip_address VARCHAR(45) NOT NULL,
country VARCHAR(100),
bytes_sent BIGINT DEFAULT 0,
bytes_received BIGINT DEFAULT 0,
FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 创建操作日志表
CREATE TABLE operation_logs (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
operation TEXT NOT NULL,
type VARCHAR(255) NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 三、访问系统 | Access the System
完成上述步骤后，你可以通过浏览器访问系统的登录页面：
```
http://your_server_domain/login.php
```
请将 `your_server_domain` 替换为你服务器的域名或 IP 地址。

## 四、注意事项 | Notes

### 安全建议 | Security
请确保你的服务器安全，建议使用防火墙和 SSL 证书。

### 数据备份 | Backup
定期备份数据库，以防止数据丢失。

### 故障排除 | Troubleshooting
如果遇到问题，请查看服务器日志文件以获取更多信息。

## 许可证 | License
```
MIT License

Copyright (c) 2025 映筑视觉INZOO

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## 关于开发者 | About the Developer
映筑视觉INZOO是一家专注于视觉设计和软件开发的团队，致力于创造高质量的数字产品和用户体验。

INZOO Visual Design is a team specializing in visual design and software development, committed to creating high-quality digital products and user experiences.

[联系开发者 | Contact Developer](https://orcid.org/0009-0005-8345-6998)
  <a
    id="cy-effective-orcid-url"
    class="underline"
     href="https://orcid.org/0009-0005-8345-6998"
     target="orcid.widget"
     rel="me noopener noreferrer"
     style="vertical-align: top">
     <img
        src="https://orcid.org/sites/default/files/images/orcid_16x16.png"
        style="width: 1em; margin-inline-start: 0.5em"
        alt="ORCID iD icon"/>
      https://orcid.org/0009-0005-8345-6998
    </a>

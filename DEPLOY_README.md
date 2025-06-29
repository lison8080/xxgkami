# 部署脚本使用说明

## 新增功能

### 1. 镜像文件本地存储
- **构建时自动保存镜像**: 运行 `./deploy.sh build` 后，镜像文件会自动保存到 `./image/` 目录
- **启动时使用本地镜像**: 运行 `./deploy.sh start` 时优先使用本地镜像文件，提高部署速度

### 2. 本机IP地址支持
- **自动获取本机IP**: 脚本会自动检测本机IP地址
- **跨机器部署**: 使用本机IP替代localhost，便于在其他电脑上访问部署的服务
- **多种检测方法**: 支持 `hostname -I`、`ip route`、`ifconfig` 等多种IP获取方式

### 3. 数据库备份和恢复
- **自动备份**: 支持一键备份Docker容器内的MySQL数据库
- **交互式恢复**: 支持从备份文件列表中选择并恢复数据库
- **时间戳命名**: 备份文件自动添加时间戳，便于管理

## 使用方法

### 基本命令
```bash
# 构建容器并保存镜像
./deploy.sh build

# 从镜像文件启动容器
./deploy.sh start

# 停止容器
./deploy.sh stop

# 重启容器
./deploy.sh restart

# 查看运行状态
./deploy.sh status

# 备份数据库
./deploy.sh backup

# 恢复数据库
./deploy.sh restore

# 显示帮助信息
./deploy.sh help
```

### 目录结构
```
xxgkami/
├── deploy.sh              # 部署脚本
├── image/                 # 镜像文件存储目录（自动创建）
│   └── xxgkami-allinone-latest.tar
├── backup/                # 数据库备份目录（自动创建）
│   └── xxgkami_backup_YYYYMMDD_HHMMSS.sql
└── docker-compose.allinone.yml
```

### 部署流程

#### 首次部署
1. 构建并保存镜像：`./deploy.sh build`
2. 启动容器：`./deploy.sh start`
3. 访问系统：`http://本机IP:19999`

#### 跨机器部署
1. 将整个项目目录复制到目标机器
2. 确保目标机器已安装Docker和Docker Compose
3. 直接启动：`./deploy.sh start`（会自动加载镜像文件）

#### 数据管理
```bash
# 定期备份数据库
./deploy.sh backup

# 需要时恢复数据库
./deploy.sh restore
```

## 注意事项

1. **权限要求**: 脚本需要sudo权限来执行Docker命令
2. **端口占用**: 确保19999端口未被其他服务占用
3. **磁盘空间**: 镜像文件较大，确保有足够的磁盘空间
4. **备份安全**: 定期备份数据库，备份文件包含敏感数据请妥善保管
5. **网络访问**: 使用本机IP地址时，确保防火墙允许19999端口访问

## 故障排除

### 常见问题
1. **容器启动失败**: 检查端口是否被占用，使用 `./deploy.sh status` 查看状态
2. **无法访问服务**: 检查防火墙设置，确保19999端口开放
3. **备份失败**: 确保容器正在运行，检查数据库连接
4. **镜像加载失败**: 检查镜像文件是否完整，重新构建镜像

### 日志查看
```bash
# 查看容器日志
sudo docker logs xxgkami-allinone

# 查看容器状态
./deploy.sh status
```

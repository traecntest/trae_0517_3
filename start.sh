#!/bin/bash

echo "============================================"
echo "低代码流程编排平台 - 一键启动脚本"
echo "============================================"

echo ""
echo "1. 检查Docker环境..."
if ! command -v docker &> /dev/null; then
    echo "❌ Docker未安装，请先安装Docker"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose未安装，请先安装Docker Compose"
    exit 1
fi

echo "✅ Docker环境正常"

echo ""
echo "2. 停止并清理旧容器..."
docker-compose down

echo ""
echo "3. 构建并启动容器..."
docker-compose up -d --build

echo ""
echo "4. 等待MySQL启动..."
sleep 15

echo ""
echo "5. 安装后端依赖..."
docker-compose exec -T backend composer install --no-interaction

echo ""
echo "6. 生成应用密钥..."
docker-compose exec -T backend php artisan key:generate --force

echo ""
echo "7. 运行数据库迁移..."
docker-compose exec -T backend php artisan migrate --force

echo ""
echo "8. 填充初始数据..."
docker-compose exec -T backend php artisan db:seed --force

echo ""
echo "9. 设置目录权限..."
docker-compose exec -T backend chmod -R 777 storage bootstrap/cache

echo ""
echo "10. 安装前端依赖..."
docker-compose exec -T frontend npm install

echo ""
echo "============================================"
echo "✅ 系统启动完成！"
echo "============================================"
echo ""
echo "后端API地址: http://localhost:8000"
echo "前端访问地址: http://localhost:3000"
echo ""
echo "默认账号:"
echo "  管理员: admin / admin123"
echo "  普通用户: zhangsan / 123456"
echo "            lisi / 123456"
echo "            wangwu / 123456"
echo ""
echo "查看日志: docker-compose logs -f"
echo "停止服务: docker-compose down"
echo ""

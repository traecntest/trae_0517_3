#!/bin/bash

echo "============================================"
echo "低代码流程编排平台 - 停止脚本"
echo "============================================"

echo ""
echo "停止后端服务..."
pkill -f "php -S 0.0.0.0:8000" 2>/dev/null || true

echo "停止前端服务..."
pkill -f "vite" 2>/dev/null || true

echo ""
echo "✅ 所有服务已停止"
echo ""
echo "检查是否还有相关进程:"
ps aux | grep -E "(php|vite|node)" | grep -v grep | head -5

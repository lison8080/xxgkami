#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
小小怪卡密验证系统 - 快速测试脚本
"""

import requests
import json
import uuid

def test_api():
    """快速测试API"""
    
    # 配置信息 - 请根据实际情况修改
    BASE_URL = "http://localhost:19999"
    API_KEY = "37973705320b619f17902e75d7638519"  # 需要从后台获取
    
    print("🚀 小小怪卡密API快速测试")
    print("-" * 40)
    
    # 生成测试设备ID
    device_id = "34dd8a14-40a0-44e5-9eac-b0c68e87e148"
    
    # 测试数据
    test_card = "iarCLN5op5y4CYgHYUu1"  # 测试卡密
    
    # POST请求测试
    print("\n📤 POST请求测试:")
    url = f"{BASE_URL}/api/verify.php"
    headers = {
        'Content-Type': 'application/json',
        'X-API-KEY': API_KEY
    }
    data = {
        'card_key': test_card,
        'device_id': device_id
    }
    
    try:
        response = requests.post(url, headers=headers, json=data)
        print(f"状态码: {response.status_code}")
        print(f"响应: {json.dumps(response.json(), indent=2, ensure_ascii=False)}")
    except Exception as e:
        print(f"错误: {e}")
    
    # GET请求测试
    print("\n📥 GET请求测试:")
    params = {
        'card_key': test_card,
        'device_id': device_id,
        'api_key': API_KEY
    }
    
    try:
        response = requests.get(url, params=params)
        print(f"状态码: {response.status_code}")
        print(f"响应: {json.dumps(response.json(), indent=2, ensure_ascii=False)}")
    except Exception as e:
        print(f"错误: {e}")

if __name__ == "__main__":
    test_api()

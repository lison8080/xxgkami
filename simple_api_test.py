#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
小小怪卡密验证系统 - 简单API测试
测试新的卡密+商品同时验证功能
"""

import requests
import json
import uuid

def test_new_api():
    """测试新的API接口"""
    
    # 配置信息 - 请根据实际情况修改
    BASE_URL = "http://localhost:19999"
    API_KEY = "37973705320b619f17902e75d7638519"  # 请替换为真实的API密钥
    
    print("🚀 小小怪卡密API测试 - 卡密+商品验证")
    print("=" * 50)
    print(f"📍 测试地址: {BASE_URL}")
    print(f"🔑 API密钥: {API_KEY[:8]}...")
    print("-" * 50)
    
    # 测试数据 - 使用数据库中实际存在的卡密
    test_cards = [
        ("knkcaIduE7ifit2nHv9I", 3),  # 商品ID=3的卡密
        ("JV0J0aD7qh4YdJflBJdy", 8),  # 商品ID=8的卡密
        ("iarCLN5op5y4CYgHYUu1", 1),  # 不存在的卡密，用于测试错误情况
    ]
    device_id = str(uuid.uuid4())

    print(f"📱 设备ID: {device_id}")
    print("-" * 50)

    # 测试每个卡密和对应的商品ID
    for test_card, correct_product_id in test_cards:
        print(f"\n🧪 测试卡密: {test_card} (应该属于商品ID: {correct_product_id})")
        print("-" * 50)

        # 测试正确的商品ID
        test_single_card(BASE_URL, API_KEY, test_card, device_id, correct_product_id)

        # 测试错误的商品ID
        wrong_product_id = 999 if correct_product_id != 999 else 1
        print(f"\n🚫 测试错误的商品ID: {wrong_product_id}")
        test_single_card(BASE_URL, API_KEY, test_card, device_id, wrong_product_id)

def test_single_card(base_url, api_key, card_key, device_id, product_id):
    """测试单个卡密和商品ID组合"""
    print(f"🏷️  测试商品ID: {product_id}")
    print("-" * 30)

    # POST请求测试
    test_post_with_product(base_url, api_key, card_key, device_id, product_id)

    # GET请求测试
    test_get_with_product(base_url, api_key, card_key, device_id, product_id)



def test_post_with_product(base_url, api_key, card_key, device_id, product_id):
    """测试POST请求（包含商品ID）"""
    print("📤 POST请求测试:")
    
    url = f"{base_url}/api/verify.php"
    headers = {
        'Content-Type': 'application/json',
        'X-API-KEY': api_key
    }
    data = {
        'card_key': card_key,
        'device_id': device_id,
        'product_id': product_id
    }
    
    try:
        response = requests.post(url, headers=headers, json=data, timeout=10)
        print(f"   状态码: {response.status_code}")
        
        try:
            json_response = response.json()
            print(f"   响应: {json.dumps(json_response, indent=4, ensure_ascii=False)}")
            
            # 解析响应
            if json_response.get('code') == 0:
                print("   ✅ 验证成功!")
                data_info = json_response.get('data', {})
                print(f"   🏷️  商品: {data_info.get('product_name', '未知')}")
                if data_info.get('card_type') == 'time':
                    print(f"   ⏰ 时间卡密，到期时间: {data_info.get('expire_time', '永久')}")
                elif data_info.get('card_type') == 'count':
                    print(f"   🔢 次数卡密，剩余: {data_info.get('remaining_count')}")
            else:
                print(f"   ❌ 验证失败: {json_response.get('message')}")
                
        except json.JSONDecodeError:
            print(f"   响应内容: {response.text}")
            
    except requests.exceptions.Timeout:
        print("   ⏰ 请求超时")
    except requests.exceptions.ConnectionError:
        print("   🔌 连接错误，请检查服务器是否运行")
    except Exception as e:
        print(f"   ❌ 错误: {e}")

def test_get_with_product(base_url, api_key, card_key, device_id, product_id):
    """测试GET请求（包含商品ID）"""
    print("📥 GET请求测试:")
    
    url = f"{base_url}/api/verify.php"
    params = {
        'card_key': card_key,
        'device_id': device_id,
        'api_key': api_key,
        'product_id': product_id
    }
    
    try:
        response = requests.get(url, params=params, timeout=10)
        print(f"   状态码: {response.status_code}")
        
        try:
            json_response = response.json()
            if json_response.get('code') == 0:
                print("   ✅ GET验证成功!")
            else:
                print(f"   ❌ GET验证失败: {json_response.get('message')}")
        except json.JSONDecodeError:
            print(f"   响应内容: {response.text}")
            
    except Exception as e:
        print(f"   ❌ 错误: {e}")

def test_missing_product_id():
    """测试缺少商品ID的情况"""
    print("\n🚫 测试缺少商品ID:")
    print("-" * 30)
    
    BASE_URL = "http://localhost:19999"
    API_KEY = "37973705320b619f17902e75d7638519"
    
    url = f"{BASE_URL}/api/verify.php"
    headers = {
        'Content-Type': 'application/json',
        'X-API-KEY': API_KEY
    }
    data = {
        'card_key': 'rO60kSHnW6QMCqRnNvVd',
        'device_id': str(uuid.uuid4())
        # 故意不包含 product_id
    }
    
    try:
        response = requests.post(url, headers=headers, json=data, timeout=10)
        json_response = response.json()
        
        if response.status_code == 400 and json_response.get('code') == 1:
            print("   ✅ 正确拒绝了缺少商品ID的请求")
            print(f"   📝 错误信息: {json_response.get('message')}")
        else:
            print("   ❌ 未正确处理缺少商品ID的情况")
            
    except Exception as e:
        print(f"   ❌ 测试错误: {e}")

if __name__ == "__main__":
    print("🎯 开始新API测试...")
    
    # 主要功能测试
    test_new_api()
    
    # 参数验证测试
    test_missing_product_id()
    
    print("\n" + "="*50)
    print("🎉 测试完成!")
    
    print("\n💡 重要说明:")
    print("   ✨ 新版API要求同时提供卡密和商品ID")
    print("   🔍 系统会验证卡密是否属于指定商品")
    print("   ❌ 如果商品中不存在该卡密，会返回'该商品中不存在此卡密'")
    print("   🔧 请确保测试时使用正确的卡密和商品ID组合")
    
    print("\n📝 使用建议:")
    print("   1. 在后台为不同商品生成对应的卡密")
    print("   2. 记录卡密与商品的对应关系")
    print("   3. 调用API时必须提供正确的商品ID")
    print("   4. 可以通过商品ID来区分不同的产品或服务")

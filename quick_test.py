#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
小小怪卡密验证系统 - 快速测试脚本
适配新版API接口，支持时间卡密和次数卡密
"""

import requests
import json
import uuid
import time
import sys
import os

# 尝试导入配置文件
try:
    from test_config import get_config, validate_config
    USE_CONFIG_FILE = True
except ImportError:
    USE_CONFIG_FILE = False

def get_test_config():
    """获取测试配置"""
    if USE_CONFIG_FILE:
        return get_config()
    else:
        # 默认配置
        return {
            'api': {
                'base_url': 'http://localhost:19999',
                'api_key': '37973705320b619f17902e75d7638519',
                'timeout': 10
            },
            'cards': {
                'general_cards': [
                    'iarCLN5op5y4CYgHYUu1',
                    'test_card_123456'
                ]
            }
        }

def test_api():
    """快速测试API"""

    # 获取配置
    config = get_test_config()
    api_config = config['api']

    print("🚀 小小怪卡密API快速测试 v2.1")
    print("=" * 50)
    print(f"📍 测试地址: {api_config['base_url']}")
    print(f"🔑 API密钥: {api_config['api_key'][:8]}...")

    # 配置验证
    if USE_CONFIG_FILE:
        errors = validate_config()
        if errors:
            print("\n⚠️  配置警告:")
            for error in errors:
                print(f"   - {error}")

    print("-" * 50)

    # 生成测试设备ID
    device_id = str(uuid.uuid4())
    print(f"📱 测试设备ID: {device_id}")

    # 获取测试卡密
    test_cards = []
    for card_type, cards in config['cards'].items():
        test_cards.extend(cards)

    if not test_cards:
        print("❌ 没有找到测试卡密，请检查配置")
        return

    # 测试多个卡密
    for i, test_card in enumerate(test_cards, 1):
        print(f"\n🧪 测试卡密 {i}: {test_card}")
        print("-" * 30)

        # 先测试不同商品ID，找到有效的商品ID
        valid_product_id = test_different_products(api_config['base_url'], api_config['api_key'], test_card, device_id)

        # 使用有效的商品ID进行POST请求测试
        test_post_request(api_config['base_url'], api_config['api_key'], test_card, device_id, valid_product_id)

        # 使用有效的商品ID进行GET请求测试
        test_get_request(api_config['base_url'], api_config['api_key'], test_card, device_id, valid_product_id)

        # 重复验证测试
        if i == 1:  # 只对第一个卡密进行重复验证测试
            test_repeat_verification(api_config['base_url'], api_config['api_key'], test_card, device_id, valid_product_id)

        print("\n" + "="*50)

def test_post_request(base_url, api_key, card_key, device_id, product_id=1):
    """测试POST请求"""
    print("\n📤 POST请求测试:")
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
        print(f"   响应头: {dict(response.headers)}")

        try:
            json_response = response.json()
            print(f"   响应体: {json.dumps(json_response, indent=4, ensure_ascii=False)}")

            # 解析响应信息
            if json_response.get('code') == 0:
                print("   ✅ 验证成功!")
                data = json_response.get('data', {})
                if data.get('card_type') == 'time':
                    print(f"   📅 时间卡密，有效期: {data.get('duration', '永久')}天")
                    print(f"   ⏰ 到期时间: {data.get('expire_time', '永久')}")
                elif data.get('card_type') == 'count':
                    print(f"   🔢 次数卡密，剩余: {data.get('remaining_count')}/{data.get('total_count')}")
                print(f"   🏷️  关联商品: {data.get('product_name', '未知')}")
                print(f"   🔄 允许重复验证: {'是' if data.get('allow_reverify') else '否'}")
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

def test_get_request(base_url, api_key, card_key, device_id, product_id=1):
    """测试GET请求"""
    print("\n📥 GET请求测试:")
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
            print(f"   响应: {json.dumps(json_response, indent=4, ensure_ascii=False)}")

            if json_response.get('code') == 0:
                print("   ✅ GET验证成功!")
            else:
                print(f"   ❌ GET验证失败: {json_response.get('message')}")
        except json.JSONDecodeError:
            print(f"   响应内容: {response.text}")

    except Exception as e:
        print(f"   ❌ 错误: {e}")

def test_different_products(base_url, api_key, card_key, device_id):
    """测试不同商品ID"""
    print("\n🏷️  不同商品ID测试:")
    url = f"{base_url}/api/verify.php"
    headers = {
        'Content-Type': 'application/json',
        'X-API-KEY': api_key
    }

    # 测试多个商品ID
    product_ids = [1, 2, 999]  # 999是不存在的商品ID

    for product_id in product_ids:
        print(f"   测试商品ID: {product_id}")
        data = {
            'card_key': card_key,
            'device_id': device_id,
            'product_id': product_id
        }

        try:
            response = requests.post(url, headers=headers, json=data, timeout=10)
            json_response = response.json()

            if json_response.get('code') == 0:
                print(f"   ✅ 商品ID={product_id} 验证成功")
                return product_id  # 返回成功的商品ID
            else:
                print(f"   ❌ 商品ID={product_id}: {json_response.get('message')}")

        except Exception as e:
            print(f"   ❌ 商品ID={product_id} 测试错误: {e}")

    return 1  # 默认返回商品ID 1

def test_repeat_verification(base_url, api_key, card_key, device_id, product_id=1):
    """测试重复验证"""
    print("\n🔄 重复验证测试:")
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
        print("   等待3秒后进行重复验证...")
        time.sleep(3)

        response = requests.post(url, headers=headers, json=data, timeout=10)
        json_response = response.json()

        if json_response.get('code') == 0:
            print("   ✅ 重复验证成功!")
            if '重复验证' in json_response.get('message', ''):
                print("   ℹ️  检测到重复验证标识")
        elif json_response.get('code') == 6:
            print("   ⚠️  此卡密不允许重复验证")
        else:
            print(f"   ❌ 重复验证失败: {json_response.get('message')}")

    except Exception as e:
        print(f"   ❌ 重复验证错误: {e}")

def test_invalid_scenarios(base_url):
    """测试无效场景"""
    print("\n🚫 无效场景测试:")
    print("-" * 30)

    invalid_api_key = "invalid_api_key"  # 无效API密钥

    url = f"{base_url}/api/verify.php"
    headers = {
        'Content-Type': 'application/json',
        'X-API-KEY': invalid_api_key
    }
    data = {
        'card_key': 'invalid_card',
        'device_id': str(uuid.uuid4())
    }

    try:
        response = requests.post(url, headers=headers, json=data, timeout=10)
        json_response = response.json()
        print(f"   无效API密钥测试: {json_response.get('message')}")

        if response.status_code == 401:
            print("   ✅ 正确拒绝了无效API密钥")

    except Exception as e:
        print(f"   ❌ 无效场景测试错误: {e}")

if __name__ == "__main__":
    print("🎯 开始API测试...")

    # 获取配置
    config = get_test_config()

    # 主要测试
    test_api()

    # 无效场景测试
    print("\n" + "="*50)
    test_invalid_scenarios(config['api']['base_url'])

    print("\n🎉 测试完成!")
    print("\n💡 提示:")
    print("   1. 请确保在后台API设置页面启用了API功能")
    print("   2. 请使用后台生成的真实API密钥")
    print("   3. 请使用系统中存在的真实卡密进行测试")
    print("   4. 如果测试失败，请检查服务器日志")
    if USE_CONFIG_FILE:
        print("   5. 可以修改 test_config.py 文件来自定义测试参数")
    else:
        print("   5. 建议创建 test_config.py 文件来自定义测试参数")

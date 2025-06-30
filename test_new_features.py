#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
小小怪卡密验证系统 - 新功能测试脚本
测试时间卡密、次数卡密、商品验证等新功能
"""

import requests
import json
import uuid
import time
import sys
from datetime import datetime

class CardAPITester:
    def __init__(self, base_url="http://localhost:19999", api_key=""):
        self.base_url = base_url
        self.api_key = api_key
        self.session = requests.Session()
        self.session.headers.update({
            'Content-Type': 'application/json',
            'X-API-KEY': api_key,
            'User-Agent': 'CardAPITester/2.0'
        })
    
    def log(self, message, level="INFO"):
        """日志输出"""
        timestamp = datetime.now().strftime("%H:%M:%S")
        icons = {
            "INFO": "ℹ️",
            "SUCCESS": "✅", 
            "ERROR": "❌",
            "WARNING": "⚠️",
            "DEBUG": "🔍"
        }
        print(f"[{timestamp}] {icons.get(level, 'ℹ️')} {message}")
    
    def test_card(self, card_key, device_id=None, product_id=None):
        """测试单个卡密"""
        if device_id is None:
            device_id = str(uuid.uuid4())
        
        url = f"{self.base_url}/api/verify.php"
        data = {
            'card_key': card_key,
            'device_id': device_id
        }
        
        if product_id:
            data['product_id'] = product_id
        
        try:
            response = self.session.post(url, json=data, timeout=10)
            return {
                'status_code': response.status_code,
                'response': response.json(),
                'device_id': device_id
            }
        except Exception as e:
            return {
                'status_code': 0,
                'error': str(e),
                'device_id': device_id
            }
    
    def test_time_card_lifecycle(self, card_key):
        """测试时间卡密的完整生命周期"""
        self.log(f"🕐 开始测试时间卡密: {card_key}")
        device_id = str(uuid.uuid4())
        
        # 首次验证
        self.log("1️⃣ 首次验证...")
        result1 = self.test_card(card_key, device_id)
        if result1.get('status_code') == 200:
            data = result1['response'].get('data', {})
            self.log(f"首次验证成功: {result1['response'].get('message')}", "SUCCESS")
            self.log(f"卡密类型: {data.get('card_type')}")
            self.log(f"有效期: {data.get('duration', '永久')}天")
            self.log(f"到期时间: {data.get('expire_time', '永久')}")
            self.log(f"允许重复验证: {'是' if data.get('allow_reverify') else '否'}")
        else:
            self.log(f"首次验证失败: {result1['response'].get('message')}", "ERROR")
            return
        
        # 重复验证测试
        self.log("2️⃣ 重复验证测试...")
        time.sleep(2)
        result2 = self.test_card(card_key, device_id)
        if result2.get('status_code') == 200:
            self.log(f"重复验证成功: {result2['response'].get('message')}", "SUCCESS")
        else:
            self.log(f"重复验证失败: {result2['response'].get('message')}", "WARNING")
        
        # 其他设备验证测试
        self.log("3️⃣ 其他设备验证测试...")
        other_device = str(uuid.uuid4())
        result3 = self.test_card(card_key, other_device)
        if result3.get('status_code') == 400:
            self.log("其他设备验证被正确拒绝", "SUCCESS")
        else:
            self.log(f"其他设备验证结果: {result3['response'].get('message')}", "WARNING")
    
    def test_count_card_lifecycle(self, card_key):
        """测试次数卡密的完整生命周期"""
        self.log(f"🔢 开始测试次数卡密: {card_key}")
        device_id = str(uuid.uuid4())
        
        # 首次验证
        self.log("1️⃣ 首次验证...")
        result1 = self.test_card(card_key, device_id)
        if result1.get('status_code') == 200:
            data = result1['response'].get('data', {})
            self.log(f"首次验证成功: {result1['response'].get('message')}", "SUCCESS")
            self.log(f"卡密类型: {data.get('card_type')}")
            self.log(f"总次数: {data.get('total_count')}")
            self.log(f"剩余次数: {data.get('remaining_count')}")
            
            remaining = data.get('remaining_count', 0)
            
            # 连续验证直到用完
            for i in range(min(remaining, 3)):  # 最多测试3次
                self.log(f"{i+2}️⃣ 第{i+2}次验证...")
                time.sleep(1)
                result = self.test_card(card_key, device_id)
                if result.get('status_code') == 200:
                    new_remaining = result['response'].get('data', {}).get('remaining_count', 0)
                    self.log(f"验证成功，剩余次数: {new_remaining}", "SUCCESS")
                    if new_remaining == 0:
                        self.log("次数已用完", "WARNING")
                        break
                else:
                    self.log(f"验证失败: {result['response'].get('message')}", "ERROR")
                    break
        else:
            self.log(f"首次验证失败: {result1['response'].get('message')}", "ERROR")
    
    def test_product_verification(self, card_key, product_ids=[1, 2, 999]):
        """测试商品验证功能"""
        self.log(f"🏷️ 开始测试商品验证: {card_key}")
        device_id = str(uuid.uuid4())
        
        for product_id in product_ids:
            self.log(f"测试商品ID: {product_id}")
            result = self.test_card(card_key, device_id, product_id)
            
            if result.get('status_code') == 200:
                data = result['response'].get('data', {})
                self.log(f"商品验证成功: {data.get('product_name')}", "SUCCESS")
            elif result.get('status_code') == 403:
                code = result['response'].get('code')
                if code == 6:
                    self.log("卡密与指定商品不匹配", "WARNING")
                elif code == 7:
                    self.log("关联商品已被禁用", "WARNING")
            else:
                self.log(f"商品验证失败: {result['response'].get('message')}", "ERROR")
    
    def test_api_security(self):
        """测试API安全性"""
        self.log("🔒 开始测试API安全性")
        
        # 测试无效API密钥
        old_key = self.session.headers.get('X-API-KEY')
        self.session.headers['X-API-KEY'] = 'invalid_key'
        
        result = self.test_card('test_card', str(uuid.uuid4()))
        if result.get('status_code') == 401:
            self.log("无效API密钥被正确拒绝", "SUCCESS")
        else:
            self.log("安全测试失败：无效API密钥未被拒绝", "ERROR")
        
        # 恢复正确的API密钥
        self.session.headers['X-API-KEY'] = old_key
        
        # 测试缺少参数
        url = f"{self.base_url}/api/verify.php"
        try:
            response = self.session.post(url, json={'card_key': 'test'}, timeout=10)
            if response.status_code == 400:
                self.log("缺少设备ID参数被正确拒绝", "SUCCESS")
        except Exception as e:
            self.log(f"参数测试错误: {e}", "ERROR")

def main():
    """主函数"""
    print("🚀 小小怪卡密系统 - 新功能测试脚本 v2.0")
    print("=" * 60)
    
    # 配置信息
    BASE_URL = "http://localhost:19999"
    API_KEY = "37973705320b619f17902e75d7638519"  # 请替换为真实的API密钥
    
    # 测试卡密 - 请替换为真实的卡密
    TEST_CARDS = {
        'time_card': 'rO60kSHnW6QMCqRnNvVd',      # 时间卡密
        'count_card': 'VZh0KU93DkKB04nM5qMO',    # 次数卡密
        'test_card': 'iMS8WC8QusqQizE7ihid'     # 通用测试卡密
    }
    
    print(f"📍 测试地址: {BASE_URL}")
    print(f"🔑 API密钥: {API_KEY[:8]}...")
    print("-" * 60)
    
    # 创建测试器
    tester = CardAPITester(BASE_URL, API_KEY)
    
    # 基础连接测试
    tester.log("🔗 测试API连接...")
    result = tester.test_card('test_connection', str(uuid.uuid4()))
    if result.get('status_code') in [200, 400]:
        tester.log("API连接正常", "SUCCESS")
    else:
        tester.log(f"API连接失败: {result.get('error', '未知错误')}", "ERROR")
        return
    
    print("\n" + "="*60)
    
    # 测试时间卡密
    if TEST_CARDS['time_card'] != 'your_time_card_here':
        tester.test_time_card_lifecycle(TEST_CARDS['time_card'])
        print("\n" + "-"*40)
    
    # 测试次数卡密
    if TEST_CARDS['count_card'] != 'your_count_card_here':
        tester.test_count_card_lifecycle(TEST_CARDS['count_card'])
        print("\n" + "-"*40)
    
    # 测试商品验证
    tester.test_product_verification(TEST_CARDS['test_card'], product_ids=["test2", "test1"])
    print("\n" + "-"*40)
    
    # 测试API安全性
    tester.test_api_security()
    
    print("\n" + "="*60)
    tester.log("🎉 所有测试完成!", "SUCCESS")
    
    print("\n💡 使用提示:")
    print("   1. 请在 TEST_CARDS 中设置真实的卡密")
    print("   2. 请确保API功能已在后台启用")
    print("   3. 请使用后台生成的真实API密钥")
    print("   4. 建议在测试环境中运行此脚本")

if __name__ == "__main__":
    main()

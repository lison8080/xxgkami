#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
小小怪卡密验证系统 - 测试配置文件
请根据实际情况修改以下配置
"""

# API服务器配置
API_CONFIG = {
    # 服务器地址（请根据实际部署地址修改）
    'base_url': 'http://localhost:19999',
    
    # API密钥（请从后台API设置页面获取）
    'api_key': '37973705320b619f17902e75d7638519',
    
    # 请求超时时间（秒）
    'timeout': 10,
    
    # 是否显示详细日志
    'verbose': True
}

# 测试卡密配置
TEST_CARDS = {
    # 时间卡密（请替换为真实的时间卡密）
    'time_cards': [
        'your_time_card_1',
        'your_time_card_2',
    ],
    
    # 次数卡密（请替换为真实的次数卡密）
    'count_cards': [
        'your_count_card_1', 
        'your_count_card_2',
    ],
    
    # 通用测试卡密
    'general_cards': [
        'iarCLN5op5y4CYgHYUu1',  # 示例卡密
        'test_card_123456',      # 不存在的卡密（用于测试错误处理）
    ]
}

# 测试商品ID列表（必须提供，API现在要求卡密+商品同时验证）
TEST_PRODUCTS = [1, 2, 999]  # 999是不存在的商品ID，用于测试
DEFAULT_PRODUCT_ID = 1  # 默认使用的商品ID

# 测试选项
TEST_OPTIONS = {
    # 是否测试重复验证
    'test_repeat_verification': True,
    
    # 是否测试不同商品ID
    'test_different_products': True,
    
    # 是否测试API安全性
    'test_api_security': True,
    
    # 是否测试无效场景
    'test_invalid_scenarios': True,
    
    # 重复验证间隔时间（秒）
    'repeat_delay': 3,
    
    # 次数卡密最大测试次数
    'max_count_tests': 3
}

# 输出配置
OUTPUT_CONFIG = {
    # 是否保存测试结果到文件
    'save_results': False,
    
    # 结果文件名
    'result_file': 'test_results.json',
    
    # 是否显示响应头信息
    'show_headers': False,
    
    # 是否显示完整的JSON响应
    'show_full_response': True
}

def get_config():
    """获取完整配置"""
    return {
        'api': API_CONFIG,
        'cards': TEST_CARDS,
        'products': TEST_PRODUCTS,
        'options': TEST_OPTIONS,
        'output': OUTPUT_CONFIG
    }

def validate_config():
    """验证配置有效性"""
    errors = []
    
    # 检查API配置
    if not API_CONFIG['base_url']:
        errors.append("API base_url 不能为空")
    
    if not API_CONFIG['api_key'] or API_CONFIG['api_key'] == '37973705320b619f17902e75d7638519':
        errors.append("请设置真实的API密钥")
    
    # 检查测试卡密
    has_real_cards = False
    for card_type, cards in TEST_CARDS.items():
        for card in cards:
            if not card.startswith('your_') and card != 'test_card_123456':
                has_real_cards = True
                break
    
    if not has_real_cards:
        errors.append("请设置至少一个真实的测试卡密")
    
    return errors

if __name__ == "__main__":
    print("🔧 测试配置验证")
    print("=" * 40)
    
    errors = validate_config()
    if errors:
        print("❌ 配置错误:")
        for error in errors:
            print(f"   - {error}")
        print("\n💡 请修改 test_config.py 文件中的配置")
    else:
        print("✅ 配置验证通过")
        
    print("\n📋 当前配置:")
    config = get_config()
    print(f"   服务器: {config['api']['base_url']}")
    print(f"   API密钥: {config['api']['api_key'][:8]}...")
    print(f"   测试卡密数量: {sum(len(cards) for cards in config['cards'].values())}")
    print(f"   测试商品数量: {len(config['products'])}")

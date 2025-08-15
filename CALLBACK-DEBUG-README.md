# OnePay代收回调调试功能说明

## 🎯 功能概述

本次实现为OnePay插件添加了完整的代收回调调试功能，支持详细记录和展示回调信息，并根据不同订单状态自动处理订单。

## ✨ 新增功能

### 1. 增强回调日志记录

**文件**: `includes/class-onepay-callback.php`

- ✅ 详细记录每个回调请求的处理过程
- ✅ 记录请求头、客户端IP、执行时间等信息
- ✅ 支持所有代收订单状态：`PENDING`、`SUCCESS`、`FAIL`、`CANCEL`、`WAIT3D`
- ✅ 新增`process_cancelled_payment()`和`process_wait3d_payment()`方法
- ✅ 完善的错误处理和日志记录

### 2. 专用调试日志器

**文件**: `includes/class-onepay-debug-logger.php`

- ✅ 新增`log_callback_received()`方法记录回调接收
- ✅ 新增`log_callback_processed()`方法记录处理结果
- ✅ 自动解析订单信息和支付数据
- ✅ 结构化存储到`wp_onepay_debug_logs`表

### 3. 管理界面回调展示

**文件**: `includes/class-wc-gateway-onepay.php`

- ✅ 在支付网关配置页显示最近10条回调记录
- ✅ 实时显示回调时间、订单号、状态、金额、处理结果
- ✅ 颜色标识不同状态（成功/失败/待处理）
- ✅ 支持AJAX刷新和查看详情弹窗

### 4. 前端界面和交互

**文件**: `assets/css/onepay-admin.css` & `assets/js/onepay-admin.js`

- ✅ 回调记录表格样式和状态颜色标识
- ✅ 详情弹窗展示完整回调数据
- ✅ AJAX刷新功能
- ✅ JSON格式化显示

### 5. 回调测试工具

**文件**: `test-callback.php`

- ✅ 独立的回调测试页面
- ✅ 支持模拟所有订单状态的回调
- ✅ 显示最近的OnePay订单列表
- ✅ 快速测试常见场景
- ✅ 回调数据预览功能

### 6. AJAX接口

**文件**: `onepay.php`

- ✅ `onepay_refresh_callbacks` - 刷新回调记录
- ✅ `onepay_get_callback_detail` - 获取回调详情

## 📋 支持的订单状态

| 状态 | 描述 | 处理逻辑 |
|------|------|----------|
| `PENDING` | 待付款 | 保持pending状态 |
| `SUCCESS` | 支付成功 | 完成支付，更新为processing |
| `FAIL` | 支付失败 | 标记为failed状态 |
| `CANCEL` | 支付取消 | 更新为cancelled状态 |
| `WAIT3D` | 等待3D验证 | 更新为on-hold，等待验证 |

## 🔧 使用说明

### 1. 启用调试模式

1. 进入 **WooCommerce > 设置 > 支付 > OnePay配置**
2. 勾选 **"启用调试日志"**
3. 保存设置

### 2. 查看回调记录

- **快速查看**: 在OnePay配置页面底部的"最近回调记录"区域
- **详细查看**: 前往 **WooCommerce > OnePay日志** 页面

### 3. 测试回调功能

1. 访问 `http://你的域名/wp-content/plugins/onepay/test-callback.php`
2. 选择一个现有订单
3. 选择要测试的状态
4. 点击"发送测试回调"

### 4. 回调URL配置

在OnePay商户后台配置回调URL为：
```
http://你的域名/?wc-api=onepay_callback
```

## 📊 日志记录内容

每个回调请求会记录以下信息：

- 📅 **回调时间**
- 🏷️ **订单号** (OnePay订单号和WooCommerce订单ID)
- 💰 **支付金额**和货币
- 🌐 **客户端IP地址**
- ⏱️ **处理执行时间**
- 📝 **完整的请求和响应数据**
- ✅/❌ **处理结果** (SUCCESS/ERROR)
- 🔍 **错误信息** (如果有)

## 🎨 界面预览

### 回调记录表格
- 时间、订单号、状态、金额、IP、结果、执行时间
- 彩色状态标识
- 详情按钮和刷新按钮

### 详情弹窗
- 基本信息、请求数据、响应数据
- JSON格式化显示
- 错误信息高亮

### 测试工具
- 订单选择表格
- 状态选择下拉框
- 快速测试按钮
- 回调数据预览

## 🔧 技术实现

### 数据库表结构
使用现有的`wp_onepay_debug_logs`表存储回调记录，包含以下关键字段：
- `log_type` = 'callback'
- `order_id`, `order_number` - 订单信息
- `amount`, `currency` - 金额信息
- `user_ip` - 客户端IP
- `request_data`, `response_data` - 请求响应数据
- `execution_time` - 执行时间
- `status` - 处理状态
- `error_message` - 错误信息

### 安全措施
- ✅ AJAX请求验证nonce
- ✅ 权限检查 (`manage_woocommerce`)
- ✅ 输入数据清理和验证
- ✅ SQL注入防护

## 📈 性能优化

- 只在调试模式下记录日志
- 限制显示最近10条记录
- AJAX异步加载，不影响页面性能
- 自动清理旧日志功能

## 🐛 故障排除

1. **回调记录不显示**
   - 确认已启用调试模式
   - 检查数据库表是否创建成功

2. **测试工具无法访问**
   - 检查用户权限 (`manage_woocommerce`)
   - 确认WordPress已正确加载

3. **AJAX请求失败**
   - 检查nonce值是否正确
   - 查看浏览器控制台错误信息

## 🎉 总结

本次实现完全满足了代收回调调试的需求：
- ✅ 详细记录所有回调信息
- ✅ 管理界面实时展示最近回调
- ✅ 支持所有订单状态的正确处理
- ✅ 提供测试工具便于调试
- ✅ 具备完善的错误处理和安全措施

现在您可以轻松监控OnePay回调请求，快速定位问题，并验证订单状态更新是否正确。
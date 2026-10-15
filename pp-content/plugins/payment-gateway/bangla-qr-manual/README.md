# Bangla QR Manual Plugin for PipaPay V2

A comprehensive payment gateway plugin for PipaPay V2 that enables automated interoperable payments through Bangla QR Manual.

## Features

### 1. Secure Automated SMS Verification

- **Mobile Number Matching**: Automatically matches SMS based on the customer's mobile number
- **Time-Based Validation**: Checks SMS within a specific time window (default: 15 minutes)
- **Masked Number Support**: Validates masked mobile numbers (e.g., 014****6340)
- **Amount Matching**: Ensures the transaction amount is within ±0.5 BDT tolerance
- **Unused SMS Tracking**: Prevents duplicate SMS usage

### 2. Automated Payment Processing

- **Automatic Confirmation**: Payment is marked as completed once SMS is verified
- **No Manual Approval Needed**: Eliminates the need for manual payment confirmation
- **Real-Time Updates**: Instant status updates across the platform

### 3. Merchant Configuration

- **Configurable Min/Max Amount**: Set minimum and maximum transaction limits
- **Charge Settings**: Configure fixed and percentage-based transaction fees
- **Status Control**: Enable or disable the payment gateway
- **Timer Duration**: Customizable time limit for SMS verification

### 4. Seamless User Experience

- **Two-Step Payment Process**: Input mobile number ? Verify via SMS
- **Visual Feedback**: Real-time progress tracking through the payment flow
- **Responsive Design**: Works flawlessly on both desktop and mobile devices
- **Clean Interface**: Professional and user-friendly payment UI

### 5. Plugin Management

- **Easy Installation**: Simple upload and activation process
- **Automatic Setup**: Auto-configures necessary settings on activation
- **Version Management**: Tracks plugin version and last updated date
- **Plugin Info**: Displays detailed information about the plugin

## How It Works

### Customer Payment Flow

1. **Select Payment Method**: Customer chooses "Bangla QR Manual" at checkout
2. **Enter Mobile Number**: Customer inputs their mobile number
3. **Amount Display**: Total payable amount (transaction + fees) is shown
4. **Mobile Verification**: Customer receives an SMS with payment details
5. **Auto Verification**: System automatically verifies the SMS
6. **Payment Complete**: Transaction marked as completed upon successful verification

### Admin Management

1. **Install Plugin**: Upload and activate the plugin through PipaPay admin
2. **Configure Settings**: Set transaction limits, fees, and timer duration
3. **Monitor Activity**: Track all payment transactions
4. **System Maintenance**: Update plugin as needed

## Installation

### Prerequisites

- PipaPay V2 platform (WordPress)
- PHP 7.4 or higher
- MySQL/MariaDB

### Manual Installation

1. **Upload Plugin**:
   - Go to your PipaPay V2 admin dashboard
   - Navigate to **System > Plugin Management**
   - Upload the `bangla-qr-manual-piprapay-v2.zip` file
   - Click **Install**

2. **Alternative Method**:
   - Extract the plugin files
   - Upload the `bangla-qr-manual` folder to `/pp-content/plugins/` directory
   - Go to **System > Plugin Management**
   - Activate the Bangla QR Manual plugin

### Network Activation (Optional)

To enable the plugin for all sites on your network:

1. Go to **System > Plugin Management**
2. Find "Bangla QR Manual" in the plugin list
3. Click **Network Activate**

## Usage

### Configuring Settings

1. Go to **System > Plugin Management**
2. Click **Edit** next to "Bangla QR Manual"
3. Configure the following fields:
   - **Display name**: Name shown to users (e.g., "Bangla QR Manual")
   - **Min amount**: Minimum transaction amount
   - **Max amount**: Maximum transaction amount
   - **Fixed charge**: Fixed fee per transaction
   - **Percent charge**: Percentage-based fee
   - **Status**: Enable or disable the plugin
   - **Timer duration**: SMS verification time in minutes
4. Click **Save** to apply changes

### Understanding Charge Calculation

The total payable amount is calculated as:

```
Total = Transaction Amount + Fixed Charge + (Transaction Amount * Percent Charge / 100)
```

**Example:**
- Transaction Amount: 1000 BDT
- Fixed Charge: 5 BDT
- Percent Charge: 2%

```
Total = 1000 + 5 + (1000 * 2 / 100)
      = 1000 + 5 + 20
      = 1025 BDT
```

## Technical Details

### Database Tables

The plugin creates the following table:

```sql
-- SMS Data Table
CREATE TABLE pp_bangla_qr_manual_sms_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL,
    sms_text TEXT,
    status ENUM('pending', 'sent', 'used', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

### SMS Verification Logic

```php
// Time Filter: 15 minutes before transaction time
$min_sms_timestamp = $tx_time - 900; // 15 * 60

// Amount Range: ±0.5 BDT tolerance
$min_val = $total_payable - 0.5;
$max_val = $total_payable + 0.5;

// Mobile Masking: 014****6340
$mobile_prefix = substr($customer_mobile, 0, 3);
$mobile_suffix = substr($customer_mobile, -4);

// Query Unused SMS
SELECT * FROM sms_data 
WHERE status != 'used' 
AND created_at >= $min_sms_timestamp
AND amount BETWEEN $min_val AND $max_val
AND mobile_number LIKE '${mobile_prefix}****${mobile_suffix}'
ORDER BY id DESC
```

## File Structure

```
/bangla-qr-manual-piprapay-v2/
+-- assets/
¦   +-- bangla-qr-manual-default.jpg
¦   +-- icon.png
+-- views/
¦   +-- admin-ui.php
¦   +-- checkout-ui.php
+-- bangla-qr-manual-class.php
+-- meta.json
+-- readme.txt
+-- upload.php
+-- bangla-qr-manual-piprapay-v2.zip
```

## File Descriptions

| File | Description |
|------|-------------|
| `bangla-qr-manual-class.php` | Main plugin class with all core logic |
| `meta.json` | Plugin metadata and configuration |
| `upload.php` | Plugin upload handler |
| `views/admin-ui.php` | Admin settings page UI |
| `views/checkout-ui.php` | Customer checkout interface |
| `assets/bangla-qr-manual-default.jpg` | Default Bangla QR Manual image |
| `assets/icon.png` | Plugin icon |
| `readme.txt` | Plugin information file |
| `bangla-qr-manual-piprapay-v2.zip` | Plugin distribution archive |

## Security Considerations

- All user inputs are properly sanitized
- Mobile number masking prevents direct exposure of customer data
- Time-based validation prevents SMS reuse
- Amount range check handles minor rounding differences
- SSL recommended for secure payment processing

## Testing

### Automated Tests

- Run `vendor/bin/phpunit` to execute unit tests
- Verify SMS verification logic with various inputs
- Test with different amount ranges and mobile number formats
- Check network activation across multiple sites

### Manual Testing

1. **Install and activate** the plugin
2. **Configure settings** in admin panel
3. **Test transaction** with valid mobile number
4. **Verify** payment is automatically completed
5. **Test edge cases** (invalid numbers, different amounts, time limits)
6. **Check network activation** across all sites
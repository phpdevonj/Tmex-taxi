<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout Successful</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .email-header {
            background-color: #2e7d32;
            padding: 32px 40px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            letter-spacing: 0.5px;
        }
        .email-header p {
            color: #c8e6c9;
            margin: 8px 0 0;
            font-size: 14px;
        }
        .email-body {
            padding: 36px 40px;
        }
        .email-body p {
            color: #444444;
            font-size: 15px;
            line-height: 1.7;
            margin: 0 0 16px;
        }
        .payout-card {
            background-color: #f1f8f1;
            border-left: 4px solid #2e7d32;
            border-radius: 4px;
            padding: 20px 24px;
            margin: 24px 0;
        }
        .payout-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .payout-card td {
            padding: 8px 0;
            font-size: 14px;
            color: #333333;
            vertical-align: top;
        }
        .payout-card td:first-child {
            font-weight: bold;
            color: #555555;
            width: 45%;
        }
        .amount-highlight {
            font-size: 22px;
            font-weight: bold;
            color: #2e7d32;
        }
        .email-footer {
            background-color: #f9f9f9;
            border-top: 1px solid #e0e0e0;
            padding: 20px 40px;
            text-align: center;
        }
        .email-footer p {
            color: #888888;
            font-size: 12px;
            margin: 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <h1>&#10003; Payout Successful</h1>
            <p>Your earnings have been sent to your bank account</p>
        </div>
        <!-- Body -->
        <div class="email-body">
            <p>Hi {{ $driver->display_name ?? $driver->first_name }},</p>
            <p>Great news! Your payout has been successfully processed and is on its way to your bank account. Below are the details of this payout:</p>
            <div class="payout-card">
                <table>
                    <tr>
                        <td>Payout Reference:</td>
                        <td><code>{{ $payoutId }}</code></td>
                    </tr>
                    <tr>
                        <td>Amount:</td>
                        <td class="amount-highlight">{{ $amount }} {{ $currency }}</td>
                    </tr>
                    <tr>
                        <td>Date &amp; Time:</td>
                        <td>{{ $payoutDate }}</td>
                    </tr>
                </table>
            </div>
            <p>Bank transfers typically take 1–3 business days to appear in your account depending on your bank.</p>
            <p>Thank you for driving with us!</p>
            <p>— The {{ config('app.name') }} Team</p>
        </div>
        <!-- Footer -->
        <div class="email-footer">
            <p>This is an automated notification. Please do not reply to this email.<br>
               If you have questions, please contact support through the app.</p>
        </div>
    </div>
</body>
</html>
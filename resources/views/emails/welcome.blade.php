<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome!</h1>
    </div>
    
    <div class="content">
        <p>Hello @if(isset($user->name)){{ $user->name }}@else there@endif,</p>
        
        <p>Welcome to {{ config('app.name', 'Our Platform') }}! We're excited to have you on board.</p>
        
        @if(isset($data['message']))
            <p>{{ $data['message'] }}</p>
        @endif
        
        <p>If you have any questions, feel free to reach out to our support team.</p>
        
        <p>Best regards,<br>{{ config('app.name', 'The Team') }}</p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>


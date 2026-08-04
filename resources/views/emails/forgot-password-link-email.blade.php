<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    <p>Hello {{ $user['agency'] ? $user['agency']['name'] : $user['agency']['first_name'] ." ".$user['agency']['last_name'] }}</p>
    <h2>We have received your request to forgot account password</h2>
    <p>You can use the following button link to set your new password:</p>
    <div>
        <button style="text-align: center; background-color: #3ec1cd; color: #fff; font-size: 16px; border: none; display: table; padding: 11px 34px; margin: 20px auto; border-radius: 8px; font-weight: 700;">
            <a style="text-decoration: none; color:white" href="{{ env('REACT_APP_URL') }}/reset-password/{{$user['eid']}}">
                Set New Password
            </a>
        </button>
        <p style="margin-top: 10px;">You can reset your password using the link below:</p>
        <a href="{{ env('REACT_APP_URL') }}/reset-password/{{$user['eid']}}" style="color: #3ec1cd;">
            {{ env('REACT_APP_URL') }}/reset-password/{{$user['eid']}}
        </a>
    </div>
    <p>Thank you!!</p>
</body>
</html>
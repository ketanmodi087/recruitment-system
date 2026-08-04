<!DOCTYPE html>
<html>
<head>
    <title>Your Invoice</title>
</head>
<body>
    <h2>Invoice Details</h2>
    <p>Dear {{ $customerData->name }},</p>
    <p>Please find attached your invoice #{{$invoice->invoice_number}}.</p>
    <p><strong>Invoice : </strong> {{$invoice->invoice_number}}</p>
    <p><strong>Name :  </strong>{{$customerData->agency->first_name}} {{$customerData->agency->last_name}}</p>
    <p><strong>Email :  </strong>{{$customerData->agency->email}}</p>
    <p><strong>Phone :   </strong>{{$customerData->agency->phone}}</p>
    <p><strong>Address :  </strong> {{$customerData->agency->address}}</p>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <style type="text/css"> 
        body
        {
            width:100%!important;
            font-size:12px;
            font-family: "Helvetica Neue",Helvetica,Arial,sans-serif!important;
            
        }
            
        *{
            font-family: "Helvetica Neue",Helvetica,Arial,sans-serif!important;
            
        }
        .container{
          width: 700px;
        }
        .outer_border{
        border:1px solid #999999!important;
        padding:4%!important;
        margin-bottom:2%!important;
        }
        .top_box{
        width:47%; padding:0%
         }
        .table_pad{
        padding:0% 0%;
        } 
        .border{
        border:1px solid #CCCCCC!important;
        }
        .small_text{
        font-size:10px!important;
        }
        .bg_color1{
          background:#965bdf;
          color: #fff;
         }
        .text_color1{
          color:#965bdf;
         }
         td{
         padding:4px;
         } 

         .table_pad tr td {
            text-align: center
         }
         .table_pad tr:nth-child(even) {
            background-color: #d7d7d7; 
        }
        .table_pad tr th {
            background-color: #965bdf !important; 
        }
        .table_pad tr:nth-child(odd) {
            background-color: #ffffff; 
        }
         </style>
</head>
<body>
    <div class="container">
        <div class="outer_border" style="position:relative"> 
            <div>
                <p style="right:10px;top:0; position:absolute">{{$invoice->invoice_number}}</p>
            </div>
            <div class="" style="position:relative" > 
                <div> 
                    <h2 class="text_color1" style="font-size:30px; box-sizing:border-box; margin-left:8px" >Company Details</h2> 
                    <div style="margin-left:15px">
                        <p style="margin-top:-20px">Agency Name : {{$customerData->agency->name}}</p>
                        <p>Name : {{$customerData->agency->first_name}} {{$customerData->agency->last_name}}</p>
                        <p>Email : {{$customerData->agency->email}}</p>
                        <p>Phone :  {{$customerData->agency->phone}}</p>
                        <p>Address :  {{$customerData->agency->address}}</p>
                    </div>
                </div> 
                <div style="position:absolute; right:0; top:0"> 
                    <h2 style="color:#965bdf;font-weight: bold;font-size:30px; text-align:right;padding-right: 30px;" id="invoice">INVOICE</h2> 
                    <table width="100%" height="70" border="0" style="margin-top:-26px;"> 
                        <tr> 
                            <td> Date</td> 
                            <td>  </td> 
                        </tr> 
                        <tr> 
                            <td width="50%">Invoice #</td> 
                            <td width="50%"></td> 
                        </tr> 
                        <tr> 
                            <td>Customer ID</td> 
                            <td></td> 
                        </tr> 
                    </table>
                </div> 
            </div> <br/>
            <div class="row"> 
                <div class=""> 
                    <table width="100%" border="0" > 
                        <tr> 
                            <td colspan="2">
                                <div class="bg_color1" style="text-indent:10px;font-size: 14px;width: 50%;height: 26px;line-height: 24px; ">
                                    BILL TO 
                                </div> 
                                <table width="100%" border="0" > 
                                    <tr> 
                                        <td width="18%">Name</td> 
                                        <td width="82%">{{ $customerData->name }}</td> 
                                    </tr> 
                                    <tr> 
                                        <td> Email</td> 
                                        <td> {{ $customerData->email }}</td> 
                                    </tr> 
                                    <tr> 
                                        <td>Phone</td> 
                                        <td>{{ $customerData->phone }}</td> 
                                    </tr> 
                                    
                                </table> 
                            </td> 
                        </tr> 
                    </table> 
                </div> 
            </div> 
            <dd style="clear:both;"></dd><br/>
            <div class="row"> 
                <table height="82" class="table_pad" style="width:100%; border:1px solid gray; "> 
                    <tr class="bg_color1"> 
                        <th width="58%" height="12" style="padding-left: 10px;">DESCRIPTION</th> 
                        <th width="13%">TAXED </th> 
                        <th style="padding-right: 10px;" width="15%" align="right">AMOUNT</th> 
                    </tr>  
                    @foreach($invoice->payment_details as $key=>$data)
                        <tr key={{$key}}>
                            <td> {{$data['description']}}</td> 
                            <td> {{$data['tax']}} </td> 
                            <td align="right"><strong>{{$data['amount']}}</strong></td>
                        </tr> 
                    @endforeach
                </table>
            </div> 

            <div class="row" style="margin-top:30px; position:relative"> 
                <div style="display:inline-block; width:60%; ">
                    <table height="82"  style="; border:1px solid gray;text-align:center;width:100%"> 
                        <tr class="bg_color1"> 
                            <th width="100%" height="12" style="padding-left: 10px;">OTHER COMMENTS</th> 
                        </tr>  
                        @foreach($invoice->other_comments as $data)
                            <tr>
                                 <td>{{$data}}</td>
                            </tr> 
                        @endforeach
                    </table>
                </div>
                <div style="display:inline-block; width:32%; position:absolute; right:0; top:-20px">
                    <hr style="margin-bottom:-3px">
                    <table height="82"  style="text-align:center;width:100%;"> 
                        <tr style="top:-50px"> 
                            <td  width="45%"><strong>Total </strong> </td>
                            <td width="55%" height="12"><strong>{{$invoice->currency['symbol']}} {{ $totalAmount}}</strong></td> 
                        </tr>  
                    </table>
                </div>
            </div> 
            <div class="row"> 
                <div  style="text-align:center"> 
                    If you have any question about this invoice, please contact <br /> <br /> <b>Thank You For Your Business!</b> 
                </div> 
            </div> 
        </div> 
    </div> 
</body>
</html>
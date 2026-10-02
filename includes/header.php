<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green-AI Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background:#f5f7fa;
        }

        .sidebar{
            width:250px;
            height:100vh;
            background:#198754;
            position:fixed;
            color:white;
            padding:20px;
        }

        .sidebar a{
            color:white;
            text-decoration:none;
            display:block;
            padding:10px;
            margin-bottom:5px;
            border-radius:5px;
        }

        .sidebar a:hover{
            background:#157347;
        }

        .content{
            margin-left:270px;
            padding:30px;
        }

        @media (max-width: 767px) {
            .sidebar{
                width:100%;
                height:auto;
                position:relative;
            }
            .content{
                margin-left:0;
                padding:15px;
            }
        }
    </style>

</head>
<body>
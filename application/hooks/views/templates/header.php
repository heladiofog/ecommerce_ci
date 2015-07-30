<html>
<head>
	<title><?php echo $title ?> - CMS GEX</title>

	<style type="text/css">
		::selection{ background-color: #E13300; color: white; }
		::moz-selection{ background-color: #E13300; color: white; }
		::webkit-selection{ background-color: #E13300; color: white; }

		body {
			background-color: #fff;
			margin: 40px;
			font: 13px/20px normal Helvetica, Arial, sans-serif;
			color: #4F5155;
		}

		a {
			color: #003399;
			background-color: transparent;
			font-weight: normal;
		}

		h1 {
			color: #444;
			background-color: transparent;
			border-bottom: 1px solid #D0D0D0;
			font-size: 19px;
			font-weight: normal;
			margin: 0 0 14px 0;
			padding: 14px 15px 10px 15px;
			clear: both;
		}

		code {
			font-family: Consolas, Monaco, Courier New, Courier, monospace;
			font-size: 12px;
			background-color: #f9f9f9;
			border: 1px solid #D0D0D0;
			color: #002166;
			display: block;
			margin: 14px 0 14px 0;
			padding: 12px 10px 12px 10px;
		}

		#body{
			margin: 0 15px 0 15px;
			border: 1px solid #000;
			margin-bottom: 10px;
			padding: 15px 5px 15px 5px;
		}

		p.footer{
			text-align: right;
			font-size: 11px;
			border-top: 1px solid #D0D0D0;
			line-height: 32px;
			padding: 0 10px 0 10px;
			margin: 20px 0 0 0;
		}

		#container{
			margin: 10px;
			border: 1px solid #D0D0D0;
			-webkit-box-shadow: 0 0 8px #D0D0D0;
		}
		
		/*para formulario*/
		
		.item_requerid {
			width: 170px;
			height: 22px;
			padding-right: 4px;
			padding-top: 4px;
			font-weight: bold;
			float: left;
			clear: left;
			text-align: right;
			/*border: 1px solid #000;*/
		}

		.form_requerid {
			width: 30%;
			/*height: 22px;*/			
			float: left;
			clear: right;
			padding: 2px 0 8px 2px;
			/*border: 1px solid #000;*/
			
		}

		/*para listado*/
		
		#list_headers {
			padding-left: 0px;
		}

		.column_header {
			width: 16%;	/*160px*/
			font-weight: bold;
			padding: 4px 0px 4px 0px;
			float: left;
			/*color: #903;*/
			/*background-color: #fd3;*/
			text-align: center;
			/*border-right: solid 2px #002166;*/
			border-bottom: solid 1px #002166;
		}
		
		#list_results {
			padding-left: 0px;
			padding: 15px 0px 15px 0px;
		}
			
			
		#list_results  ul{
			margin: 0;
			padding: 0;
			clear: both;
		}
		
		#list_results  ul li{
			width: 16%;		/*145px;*/
			padding: 4px 0px 4px 0px;
			list-style-type: none;
			display: inline;
			float: left;
			font-size: 13px;
			/*border: 1px solid #000;*/
			text-align: center;
			/*background-color: #fa3;*/
		}

		#list_results  li a{
			width: 100px;	
			font-weight: bold;
			color:#002166;
			text-decoration: none;
		}
		
		#list_results  li a:hover{
			color:#fb3;
		}
		
		.form_button {
			/*padding: 15px 0px 15px 0px;*/
			margin-left: 20%;
			clear: both;
		}
	</style>
</head>
<body>

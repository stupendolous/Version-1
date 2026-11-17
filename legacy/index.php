<!DOCTYPE html>
<html>
<head>
	<title>KIDS TOYS</title>
</head>
<body>
	<center>
		<h2>SENARAI BARAN MAINAN</h2>
<body style = "background-color:#D77EF0;">
   
		
		<table border="1" cellspacing="0" cellpadding="6">
		  <tr bgcolor="#eee">	

			<th>KOD PRODUK</th>
			<th>NAMA PRODUK</th>
        		<th>HARGA</th>
        		<th>KUANT</th>

<td></td>
			
		  </tr>

		  <?php
		  	include __DIR__ . '/../config.php';

		  	$papar=mysqli_query($connect,"SELECT * FROM jadualmainan");

		  	while ($row=mysqli_fetch_array($papar)) {
		  		$row=array_map(fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $row);
		  		
		  echo "
		  <tr>	
			<td>".$row['kod_produk']."</td>
			<td>".$row['nama_produk']."</td>
        		<td>".$row['harga']."</td>
        		<td>".$row['kuantiti']."</td>
<td><a href='delete.php?kod_produk=".urlencode(html_entity_decode($row['kod_produk']))."' onclick=\"return confirm('Hapus rekod ini?')\"> Hapus </a></td>
		  </tr>";
		}
		  ?>
		</table>
<p><a href="add.php">Tambah Rekod</a> | <a href="ai.php">AI Company Control Center</a></p>
		
	</center>

	
</body>
</html>



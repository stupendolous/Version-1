<html>
<body>
<?php
include('config.php');


if(isset($_POST['submit']))
{

$kod_produk=($_POST['kod_produk']);
$nama_produk=($_POST['nama_produk']);
$harga=($_POST['harga']);
$kuantiti=($_POST['kuantiti']);


$stmt = mysqli_prepare($connect, "INSERT INTO jadualmainan (kod_produk, nama_produk, harga, kuantiti) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'ssdi', $kod_produk, $nama_produk, $harga, $kuantiti);
mysqli_stmt_execute($stmt);

	header("location:index.php");
	exit;
}
?>


<center>
<fieldset style="width:500px;">
<h4>TAMBAH REKOD MAINAN</h4>

<form method="post" action="">
KOD PRODUK: <input type="text" name="kod_produk"><br>
NAMA PRODUK: <input type="text" name="nama_produk"><br>
HARGA : <input type="text" name="harga"><br>
KUANTITI : <input type="text" name="kuantiti"><br>
<br>
<input type="submit" name="submit">
</form>

</fieldset>
</center>
</body>
</html>
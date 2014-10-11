<?php

if(!$_POST) goto theend;

//Obviously this isn't the slightest bit secure, but it doesn't need to be.
$hashStuff = json_decode(file_get_contents("secret/hash.php"));

if(hash_hmac("sha256",$_POST["password"],$hashStuff[0]) != $hashStuff[1]){
    $result = ":P";
    goto theend;
}

foreach($_POST as $type => $words){
    if($type == "password") break;
    $words = explode(",\r\n",str_replace(",,",",",$words));
    foreach($words as $word){
        if(!empty($word)) $newDictionary[$type][] = $word;
    }
    natcasesort($newDictionary[$type]);
    $newDictionary[$type] = array_values($newDictionary[$type]);
}

$time = time();
file_put_contents("dictionary.json",json_encode($newDictionary));
$result = ":)";

theend:

?>
<!DOCTYPE html>
<html>
<head>
<title>Go Away Plox</title>
<style>
*
{
box-sizing:border-box;
}
textarea
{
display:block;
height:500px;
width:100%;
font-size:18px;
line-height:36px;
resize:none;
overflow-x:scroll;
white-space:nowrap;
}
body
{
text-align:center;
}
div
{
display:inline-block;
width:200px;
margin:0px 10px;
}
h1
{
text-align:center;
margin:0;
}
input[type=submit]
{
display:inline-block;
cursor:pointer;
margin:20px auto;
border:0;
border-radius:10px;
width:200px;
height:50px;
background-color:#6699cc;
font-size:36px;
line-height:50px;
color:white;
}
input[type=submit]:hover
{
background-color:#ff9933;
}
</style>
</head>
<body>
<form method="post" action="dictionary.php" target="_top" enctype="multipart/form-data">

<?php

$dictionary = json_decode(file_get_contents("dictionary.json"),true);

foreach($dictionary as $type => $words){
    echo "<div><h1>$type</h1><textarea name=\"$type\">";
    natcasesort($words);
    foreach($words as $word){
        echo "$word," . PHP_EOL;
    }
    echo "</textarea></div>";
}

?><br />
<?php echo $result ?><input type="text" name="password" /><br />
<input type="submit" value="Submit" />
</form>
</body>
</html>

<?

function _array_column($array,$key){
    foreach($array as $row) $return[] = $row[$key];
    return $return;
}

function sentenceOrder($a,$b){
    global $typeValues;
    return $typeValues[$a] - $typeValues[$b];
}

function oxfordComma($array,$key,$field){
    if($array[$key][$field] == $array[$key + 1][$field]){
        if($array[$key][$field] == $array[$key + 2][$field]){
            return ",";
        }else if($array[$key][$field] == $array[$key - 1][$field]){
            return ", and";
        }else{
            return " and";
        }
    }
}

$typeOptions = array("adjective","noun","adverb","verb","location");
$default = array("adjective","noun","verb","noun");
$vowels = array("a","e","i","o","u");
$prepositions = "/\s(aboard$|about$|above$|across$|after$|against$|along$|alongside$|amid$|among$|anti$|around$|as$|at$|before$|behind$|below$|beneath$|beside$|besides$|between$|beyond$|but$|by$|concerning$|considering$|despite$|down$|during$|except$|excepting$|excluding$|following$|for$|from$|in$|inside$|into$|like$|minus$|near$|of$|off$|on$|onto$|opposite$|outside$|over$|past$|per$|plus$|regarding$|round$|save$|since$|than$|through$|to$|toward$|towards$|under$|underneath$|unlike$|until$|up$|upon$|versus$|via$|with$|within$|without)/i";

$x = 0;
foreach($typeOptions as $type){
    $typeValues[$type] = $x;
    $x++;
}

$dictionary = json_decode(file_get_contents("dictionary.json"),true);

//Get params from URL. If a number, return that many random word types.
$order = @array_slice($_GET["prompt"],0,20);
if(empty($_GET) || empty($_GET["prompt"])) $order = $default;
if(!is_array($_GET["prompt"])) $order = array($_GET["prompt"]);
foreach($order as $key => $val){
    if(is_numeric($val)){
        $order = null;
        if($val > 20) $val = 20;
        for($x = 0; $x < $val; $x++){
            $order[] = $typeOptions[array_rand(array_keys($typeOptions))];
        }
        break;
    }
    $val = preg_replace("/[0-9]+/","",$val);
    if(!in_array($val,$typeOptions) || empty($_GET)){
        $order = $default;
        break;
    }
    $order[$key] = $val;
}

//$order = json_decode('["verb","noun","adjective","adverb","adverb","verb","adjective","verb","location","location","noun","noun","adjective","location","verb"]');

//Split into objects by type (noun or verb).
$objectNum = 0;
foreach($order as $type){
    $objects[$objectNum]["words"][] = $type;
    $objects[$objectNum]["objectType"] = $type;
    if(in_array($type,array("noun","verb"))) $objectNum++;
}

foreach($objects as $objectNum => $object){
//Sort and do any necessary replacing within objects to make grammatic sense.
    foreach($object["words"] as $key => $type){
        if(count($order) > 1){
            switch($object["objectType"]){
                case "noun": if($type == "adverb") $type = "adjective"; break;
                case "verb": if($type == "adjective") $type = "adverb"; break;
                case "location":
                    if(in_array($type,array("adverb","adjective"))) $type = "location";
                    if($objectNum == count($objects) - 1) break;
                default: $type = "noun"; $object["objectType"] = $type; break;
            }
        }
        $object["words"][$key] = $type;
    }
    usort($object["words"],"sentenceOrder");

    foreach($object["words"] as $key => $type){
        $newOrder[] = $type;
//Get words from dictionary. No duplicates.
        do{
            $word = $dictionary[$type][array_rand($dictionary[$type])];
        }while(@in_array($word,$wordsUsed));
        $wordsUsed[] = $word;
        $object["words"][$key] = array($type,$word);
    }
    $objects[$objectNum] = array(
        "words" => $object["words"],
        "objectType" => $object["objectType"]
    );
}

foreach($objects as $objectNum => $object){
    $objectType = $object["objectType"];

//Before object
    if($objectType == "noun"){
        $sentence[$objectNum] .= (
            in_array(
                strtolower(
                    substr($object["words"][0][1],0,1)
                )
            ,$vowels) ? " an" : " a");
    }else if($objectType == "verb"){
        if(
            $objects[$objectNum - 1]["objectType"] == "noun"
          &&count($objects) > 3
        ){
            $sentence[$objectNum] .= " who is";
        }
    }

//In object
    $types = _array_column($object["words"],"0");
    $typeTotal = array_count_values($types);
    $typeCount = array();
    foreach($object["words"] as $key => $pair){
        $type = $pair[0];
        $word = $pair[1];

//In word
        if($type == "verb"){
            if(
                $objects[$objectNum + 1]["objectType"] == "verb"
              ||$objects[$objectNum + 1]["objectType"] == "location"
              ||empty($objects[$objectNum + 1])
              ||$objects[$objectNum]["words"][$key + 1][0] == "location"
            ){
                $word = preg_replace($prepositions,"",$word);
            }
        }
        $sentence[$objectNum] .= " $word";

//After word
        $typeCount[$pair[0]]++;
        if($type == "adjective"){
            if($typeTotal[$type] > 1 && $typeCount[$type] < $typeTotal[$type]){
                $sentence[$objectNum] .= ",";
            }
        }
        if($type == "adverb"){
            $sentence[$objectNum] .= oxfordComma($object["words"],$key,0);
        }
        if(
            $type == "location"
          &&$key == count($object["words"]) - 1
          &&$objectType == "verb"
          &&$objects[$objectNum + 1]["objectType"] == "noun"
        ){
            $sentence[$objectNum] .= " with";
        }
    }

//After object
    if(in_array($objectType,array("noun","verb"))){
        $sentence[$objectNum] .= oxfordComma($objects,$objectNum,"objectType");
    }
}

foreach($sentence as $word){
    $output .= $word;
}

$result = ucfirst(trim($output)) . ".";

if($counter = file_get_contents("counter.txt")){
    $counter++;
    file_put_contents("counter.txt", $counter);
}

if(isset($_GET["api"])) die($result);

$twitter = "https://twitter.com/intent/tweet?text=%23ineedaprompt%20" . urlencode($result);
$reddit = "http://reddit.com/r/ineedaprompt/submit?selftext=true&title=" . urlencode($result);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta name="description" content="A prompt generator for drawing or writing." />
<meta name="viewport" content="width=800" />
<link rel="stylesheet" type="text/css" href="indexcss.css" />
<link rel="icon" href="favicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<title>I Need A Prompt</title>
<base target="_blank" />
</head>
<body>
<header>
<form method="get" action="." target="_top" enctype="multipart/form-data">
<h1><span><?php

echo $result;

?></span></h1>
<ul>
<li><a href="<?php echo $twitter; ?>">Tweet it: &num;ineedaprompt</a></li>
<li><a href="<?php echo $reddit; ?>">Post it to /r/ineedaprompt</a></li>
</ul>
<ul id="options">
<?php

$sentenceOrder = array(
    "adjective",
    "adjective",
    "noun",
    "adverb",
    "verb",
    "adjective",
    "adjective",
    "noun",
    "location"
);

$used = array();
do{
    foreach($sentenceOrder as $key => $type){
        reset($newOrder);
        $isChecked = "";
        $used[$type][] = 1;
        $label = $type . count($used[$type]);
        if($type == current($newOrder)){
            $isChecked = "checked=\"checked\"";
            unset($newOrder[key($newOrder)]);
        }
        echo <<<EOF
<li><input name="prompt[]" value="$label" type="checkbox" id="$label" $isChecked><label for="$label">$type</label></li>

EOF;
    }
    $sentenceOrder = $newOrder;
}while(count($newOrder) > 0);

?>
</ul>
<button type="submit"><span class="button">I need a prompt!</span><small><?php echo $counter; ?></small></button>
</form>
</header>

<main>
<nav>
<ul>
<li><label for="toggleDic">Dictionary</label></li>
<li><label for="toggleApi">API</label></li>
<li><a href="https://github.com/robertgfthomas/ineedaprompt">GitHub</a></li>
<li><a href="http://www.robertakarobin.com">RobertAKARobin</a></li>
<li><a href="mailto:hello@robertakarobin.com">Contact</a></li>
<li><a href="https://twitter.com/search?f=realtime&q=ineedaprompt">#ineedaprompt</a></li>
</ul>
</nav>
<input type="radio" name="toggle" id="toggleDic" />
<article id="dictionary">
<h2><label for="toggleDic">The INAP Dictionary</label></h2>
<p>All words and phrases are (I think) PG and suitable for all ages. Please <a href="mailto:hello@robertakarobin.com">e-mail me</a> or <a href="http://www.reddit.com/r/ineedaprompt/comments/1uh2fy/have_ideas_for_new_words_or_phrases/">post on Reddit</a> to suggest new ones!</p>
<dl>
<?php

$total = 0;
foreach($dictionary as $type => $words){
    natcasesort($words);
    $dictionary[$type] = $words;
}
foreach($dictionary as $type => $words){
    echo "<dt>" . ucfirst($type) . "s (" . count($words) . ")</dt>";
    foreach($words as $word){
        $total++;
        echo "<dd>" . ucfirst($word) . "</dd>";
    }
}
?>
</dl>
</article>

<input type="radio" name="toggle" id="toggleApi" />
<article id="api">
<h2><label for="toggleApi">The INAP API</label></h2>
<p>The INAP dictionary is available as JSON <a href="dictionary.json">here</a>.</p>
<p>To get a prompt, just make a GET request to <code><a href="http://ineedaprompt.com/index.php?api">ineedaprompt.com?api</a></code>. Omitting the <code>api</code> will return the full webpage (what you're looking at now).</p>
<p>Specify your prompt's terms and their order by adding a <code>prompt[]</code> parameter for each term. Accepted values are <code>adjective</code>, <code>noun</code>, <code>adverb</code>, <code>verb</code> and <code>location</code>.</p>
<p>For example:</p>
<p><code><a href="index.php?api&amp;prompt%5B%5D=noun&amp;prompt%5B%5D=verb&amp;prompt%5B%5D=adjective&amp;prompt%5B%5D=adverb&amp;prompt%5B%5D=location&amp;prompt%5B%5D=noun">http://ineedaprompt.com?api&amp;prompt[]=noun&amp;prompt[]=verb&amp;prompt[]=adjective&amp;prompt[]=adverb&amp;prompt[]=location&amp;prompt[]=noun</a></code></p>
<p>If you instead specify a number, the API will return a prompt with that many terms of randomly-selected types.</p>
<p>For example:</p>
<p><code><a href="index.php?api&amp;prompt=15">http://ineedaprompt.com?api&amp;prompt=15</a></code></p>
<p>The API will return up to 20 terms.</p>

</article>

</main>
</body>
</html>

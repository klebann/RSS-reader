<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Backend - zadanie testowe</title>
</head>

<body>

    <?php
    $logger = new Logger();
    $logger->scriptStarted();
    
    //include config
    require_once 'config.php';

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query('SET NAMES '.$charset);

    // Check connection
    if ($conn->connect_error) {
      die("Connection failed: ".$conn->connect_error."<br>");
    }
    echo "Connected successfully<br>";
    
    foreach($urls as $url){
        if(@simplexml_load_file($url))
            $feed = simplexml_load_file($url);
        else
            echo "Invalid RSS feed URL in: ".$url."<br>";

        $i = 0;
        if(!empty($feed)){
            
            if ($feed->channel){
                $items = $feed->channel->item;
                $siteName = $feed->channel->title;
            }
            else if ($feed->entry){
                $items = $feed->entry;
                $siteName = $feed->title;
            }   
            
            echo $siteName."<br>";
            foreach($items as $item){
                
                //only 5 articles
                if($i >= 5)
                    break;
                
                $title = $conn->real_escape_string($item->title);
                $pubDate = getPublishedDate($item);
                $content = $conn->real_escape_string(getDescription($item));
                $link = getLink($item);

                //check if exists in database
                $q="SELECT COUNT(1) FROM articles WHERE link='$link'";
                $row=mysqli_fetch_row($conn->query($q));
                if($row[0] >= 1)
                    print "Article already exists<br>";
                else{
                    $sql = "INSERT INTO articles (title, date, content, link) VALUES ('$title','$pubDate','$content','$link')";

                    if ($conn->query($sql))
                        $logger->newRecord($conn->insert_id);
                    else
                        echo "Error: " . $sql . "<br>" . $conn->error;
                }        

                $i++;
            }
        }
        else
            echo "No item found in: ".$url."<br>";
    }
    
    $conn->close();
    $logger->scriptEnded();
    
    function getPublishedDate($item){
        if ($item->pubDate)
            return date('Y-m-d H:i:s', strtotime($item->pubDate));
        else if ($item->published)
            return date('Y-m-d H:i:s', strtotime($item->published));
    }
    
    function getDescription($item){
        if ($item->children('media', true)->description)
            return (string)$item->children('media', true)->description;
        else if ($item->description)
            return $item->description;
        else if ($item->content)
            return $item->content;
    }
    
    function getLink($item){
        if($item->link['href'])
            return $item->link['href'];
        else if($item->link)
            return $item->link;
        else if($item->id)
            return $item->id;
    }
    
    class Logger{
        private $file;
        
        function __construct() {
            $this->file = fopen("logs.txt", "a");
        }
        
        function __destruct() {
            fclose($this->file);
        }
        
        function scriptStarted(){
            $log = date('Y-m-d H:i:s')." - Script STARTED\n";
            fwrite($this->file,$log);
        }
        
        function newRecord($id){
            $log = date('Y-m-d H:i:s')." - New record ID:".$id." added successfully\n";
            fwrite($this->file,$log);
            
            echo "New record ID:".$id." added successfully<br>";
        }
        
        function scriptEnded(){
            $log = date('Y-m-d H:i:s')." - Script ENDED\n";
            fwrite($this->file,$log);
        }
    }
    ?>
</body>
</html>
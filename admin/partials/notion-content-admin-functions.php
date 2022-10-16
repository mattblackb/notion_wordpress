<?php
 function return_html_notion_content($block_row, $arrAnnotations, $bulleted_list_item, $numbered_list_item, $return_content = true) {
  // error_log('return_html_notion_content' . print_r($block_row, true));
    $block_content = "";
	$block_type = $block_row["type"];
	$block_id = $block_row["id"];
 
    if(isset($block_row[$block_type]["external"])){		
        $open_tag = "";
            $close_tag = "";
        if(isset($block_row[$block_type]["external"]["caption"][0])){
            $block_content = "<img src='".$block_row[$block_type]["external"]["url"]."' alt='".$block_row[$block_type]["external"]["caption"][0]["plain_text"]."'>";
        } else {
            $block_content = "<img src='".$block_row[$block_type]["external"]["url"]."'>";
        }
    }
    if(isset($block_row[$block_type]["rich_text"])) {
        // error_log(print_r($block_row[$block_type]["rich_text"][0]['text'], true));
        foreach($block_row[$block_type]["rich_text"] AS $block_text) {
            
            reset($arrAnnotations);
            $open_tag = "";
            $close_tag = "";
            foreach($arrAnnotations AS $ntag => $html_tag) {
                if($block_text["annotations"][$ntag]) {
                    $open_tag .= "<$html_tag>";
                    $close_tag = "</$html_tag>" . $close_tag;
                }
            }
            if(isset($block_text["text"]["link"])) {
                $block_content_variable = "<a href='" . $block_text["text"]["link"]["url"] . "' target='_blank'>" . $block_text["text"]["content"] . "</a>";
            } else{
                $block_content_variable = $block_text["text"]["content"];
            }
            $block_content .= $open_tag . $block_content_variable . $close_tag;
        }
    }
    $pre = "";
    if($block_type != "bulleted_list_item" && $bulleted_list_item) {
        $pre = "</ul>\n";
        $bulleted_list_item = false;
    }
    if($block_type != "numbered_list_item" && $numbered_list_item) {
        $pre = "</ol>\n";
        $numbered_list_item = false;
    }
   
    switch($block_type) {
        case "heading_1":
            $block_content = "$pre<h1>$block_content</h1>\n";
            break;
        case "heading_2":
            $block_content = "$pre<h2>$block_content</h2>\n";
            break;
        case "heading_3":
            $block_content = "$pre<h3>$block_content</h3>\n";
            break;
        case "paragraph":
            //check for links within string and replace with <a> tag if found
            $block_content = "$pre<p>$block_content</p>\n";
            break;
        case "quote":
            $block_content = "$pre<blockquote>$block_content</blockquote>\n";
            break;
        case "to_do":
            $block_content = "\t<input type='checkbox' name='$block_id' id='$block_id'> $block_content<br>\n";
            break;
        case "divider":
            $block_content = "<hr>\n";
            break;
        case "callout":
            $block_content = "$pre<div class='callout'>$block_content</div>\n";
            break;
        case "table";
        error_log(print_r($block_content, true));
            
            break;
        case "bulleted_list_item":
            if(!$bulleted_list_item) {
                $bulleted_list_item = true;
                $block_content = "<ul>\n\t<li>$block_content</li>\n";
            }
            else {
                $block_content = "\t<li>$block_content</li>\n";
            }
            break;
        case "numbered_list_item":
            if(!$numbered_list_item) {
                $numbered_list_item = true;
                $block_content = "<ol>\n\t<li>$block_content</li>\n";
                
            }
            else {
                $block_content = "\t<li>$block_content</li>\n";
            }
            break;
        }
        if(isset($block_content)) {
           
           return $block_content;
        } else {
            return "";
        }
    } 
    if(isset($bulleted_list_item)) {
        $page_content .= "</ul>\n";
    }
    if(isset($numbered_list_item)) {
        $page_content .= "</ol>\n";
    }
    
        
    

?>

<div class="wrap">
    <h1 class="wp-heading-inline">Flux Telecharges</h1>
</div>

<?php
// opening a directory 
$dir_handle = opendir(WPIF_DIR.'/flux'); 
?>
<div class="list-loading-file">
</div>

<?php
echo "<div class='admin-folder'>";

echo "<hr>";

echo "<div class='list-item-folder' id='folders'>";
$dir_handle = opendir(WPIF_DIR.'flux'); 
$list_files = array();
while(($file_name = readdir($dir_handle)) !== false) {
    if($file_name !== '.' && $file_name !== '..' && $file_name !== 'archive' && $file_name !== '.DS_Store')
    {
        $name = $file_name;
        $id = 0;
        $url = WPIF_URL;
?>
        <div class="item-folder" id="excel-<?php echo $id?>">
            <img src="<?php echo $url ?>/template/img/folder.png" />
                <label><?php echo $name ?></label>
                <div class="expand-folder">
                <div class="update">
                </div>
                <div class="options">
                    <!-- <button><span  target="excel-'.$id.'" class="dashicons dashicons-update"></span></button> -->
                    <button><a href="<?php echo WPIF_DIR ?>flux/<?php echo $name ?>"><span  target="excel-'.$id.'" class="dashicons dashicons-download"></span></a></button>
                    <button><span target="excel-<?php echo $id ?>" class="dashicons  dashicons-no"></span></button>
                    </div>
                </div>
            </div>
        </div>
<?php

    }
}
// closedir($dir_handle);
echo "</div>";

// closing the directory 
closedir($dir_handle); 
?> 
<script type="text/javascript">
    var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>",c=0;
    
    function intEvent(){

        jQuery(".expand-folder .options button .dashicons-no").click(
            function(e){
                var element = this;
                var target = jQuery(element).attr("target");
                var r = confirm("Voulez vous vraiment supprimer ce fichier ?");
                if (r == true) {
                    jQuery.get( 
                        ajaxurl,
                        {
                            'action': 'import_flux_ajax_request',
                            'function': 'remove_element',
                            'url_file':jQuery("#"+target+" label").html()
                        }, 
                        function( data ) {
                            jQuery("#"+target).hide(1000);
                        },
                        'json'
                    );                
                } else {
                  
                }
            }
        );
    }
    jQuery(document).ready(
        function(){
            intEvent();
        }
    );
</script>
<style type="text/css">
    div#folders {
        display: block;
    }

    div#folders .item-folder {
        width: 10%;
        display: inline-block;
        min-width: 150px;
        margin: 15px;
        text-align: center;
            position: relative;
    }
    div#folders .item-folder:hover img {
        transform: scale(1.2);
        transition: transform .5s ease;
    }
    div#folders .item-folder img {
        width: 75%;
    }
    div#folders .item-folder label {
        display: block;
    }
    .expand-folder{
        position: absolute;
        top: 30%;
        width: 100%;
    }
    .expand-folder .options {
        display: flex;
        width: 100%;
        justify-content: center;
    }
    .expand-folder .options button {
        background: #d6d4d3;
        border: none;
        width: 30%;
        padding: 10px;
        cursor: pointer;
    }
    .expand-folder .options button:hover {
        background: white;
    }
    .list-loading-file > div {
        margin: 6px;
        padding: 8px 25px 8px 20px;
        width: max-content;
    }
    .list-loading-file .success{
        background: #e4eae4;
    }
    .list-loading-file .fail{
        background: #ffd2d2;
    }
    .list-loading-file .success span {
        color: green;
    }

    .list-loading-file .fail span {
        color: red;
    }
    .rotate {
        -webkit-animation:spin 1s linear infinite;
        -moz-animation:spin 1s linear infinite;
        animation:spin 1s linear infinite;
    }
    @-moz-keyframes spin { 100% { -moz-transform: rotate(360deg); } }
    @-webkit-keyframes spin { 100% { -webkit-transform: rotate(360deg); } }
    @keyframes spin { 100% { -webkit-transform: rotate(360deg); transform:rotate(360deg); } }

</style>

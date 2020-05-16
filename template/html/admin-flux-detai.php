<?php 
    $post_id = 0;
    $etat = 0;
    $url = '';
    if(isset($_GET['post'])){
        $post_id = $_GET['post'];
        $etat = get_post_meta($post_id, 'flux_stape', true);
        $url = get_post_meta($post_id, 'flux_url', true);
    }
    // echo get_post_meta($post_id, 'flux_stape', true);
    // echo get_post_meta($post_id, 'flux_url', true);
?>
<div action="" id="manif-details" >   
    <div class="double">
        <div style="width: 100%">
            <fieldset>
                 <label for="flux_stape">Etat de Chargement du Flux :</label>
                <select name="flux_stape" id="flux_stape" >
                    <option value="-1" <?php if($etat==0)echo 'selected="selected"';?>>
                        En Attente de telechargement 
                    </option>
                    <option value="1" <?php if($etat==1)echo 'selected="selected"';?>>
                        En cours de telechargement 
                    </option>
                    <option value="2" <?php if($etat==2)echo 'selected="selected"';?>>
                        Telechargement Termine 
                    </option>
                    <option value="3" <?php if($etat==3)echo 'selected="selected"';?>>
                        En cours d'importation de produits 
                    </option>
                    <option value="4" <?php if($etat==4)echo 'selected="selected"';?>>
                        Importation de produits terminee
                    </option>
					<option value="-2" <?php if($etat==-2)echo 'selected="selected"';?>>
                        Annuler l'import du flux actuel
                    </option>
                </select>
            </fieldset>
            <div class="double">
                <div style="width: 100%">
                             
                    <label for="flux_url"><?php _e( 'Url du flux :', 'wp_manifestation_manage' ); ?></label>
             
                    <input name="flux_url" type="url" id="flux_url" value="<?php echo $url; ?>" />
                </div>

         
            </div>
        </div>
    </div>
</div>
<style type="text/css">
    #manif-details div.double {
        display: flex;
        justify-content: space-between;
    }
    #manif-details label {
        display: block;
        font-size: 15px;
        margin: 5px;
    }
    #manif-details input {
        width: 100%;
        height: 45px;
        color: #7d7d7d;
    }
    #manif-details select {
        height: 40px !important;
    }

    .double-many > div.hide-label{
            display: none;
    }
    .double-many > div img {
        width: 100% !important;
    }

    .double-many > div {
        width: 30% !important;
        display: inline-block;
    }
    .double-many {
        display: block;
    }
</style>

 
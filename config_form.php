<div class="field">
    <div class="field-meta">
        <label for="ohms_embed_height"><?php echo __('Embed Height'); ?></label>
    </div>
    <div class="inputs">
        <p class="explanation">
            <?php echo __("Height for OHMS embeds, in pixels."); ?>
        </p>
        <?php echo get_view()->formText('ohms_embed_height', get_option('ohms_embed_height')); ?>
    </div>
</div>
<div class="field">
    <div class="field-meta">
        <label for="ohms_embed_extract_metadata"><?php echo __('Extract Metadata'); ?></label>
    </div>
    <div class="inputs">
        <p class="explanation">
            <?php echo __('Whether to extract metadata from OHMS XML files when they are added and set it on the parent item. When enabled, items that have OHMS XML files added to them will also be set to the Oral History item type.'); ?>
        </p>
        <?php echo get_view()->formCheckbox('ohms_embed_extract_metadata', get_option('ohms_embed_extract_metadata'), null, array('checked' => 1)); ?> 
    </div>
</div>

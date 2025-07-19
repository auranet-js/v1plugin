<?php
global $product;
$image_id = $product->get_image_id();
$gallery_ids = $product->get_gallery_image_ids();
$attributes = $product->get_attributes();
?>
<style>
    .title { font-size: 20pt; font-weight: bold; text-align: center; }
    .price { font-size: 16pt; color: #d00; }
    .attribute-name { font-weight: bold; }
    .product-image { text-align: center; margin-bottom: 20px; }
</style>

<div class="title"><?= $product->get_name(); ?></div>

<?php if ($image_id) : ?>
<div class="product-image">
    <?php 
    $image_path = get_attached_file($image_id);
    echo '<img src="' . $image_path . '" style="max-width: 300px;" />';
    ?>
</div>
<?php endif; ?>

<div class="price">
    Cena: <?= wc_price($product->get_price()); ?>
</div>

<h2>Opis produktu</h2>
<div><?= wpautop($product->get_description()); ?></div>

<?php if (!empty($attributes)) : ?>
<h2>Specyfikacja</h2>
<table border="1" cellpadding="5">
    <?php foreach ($attributes as $attribute) : ?>
    <tr>
        <td class="attribute-name"><?= wc_attribute_label($attribute->get_name()); ?></td>
        <td><?= implode(', ', $attribute->get_options()); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!empty($gallery_ids)) : ?>
<h2>Galeria produktu</h2>
<?php foreach ($gallery_ids as $gallery_id) : ?>
    <?php 
    $image_path = get_attached_file($gallery_id);
    echo '<img src="' . $image_path . '" style="max-width: 200px; margin: 10px;" />';
    ?>
<?php endforeach; ?>
<?php endif; ?>
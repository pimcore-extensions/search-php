<?php if(is_array($this->suggestions)): ?>
<ul>
    <?php foreach($this->suggestions as $suggestion): ?>
        <li><?=$suggestion['q']?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

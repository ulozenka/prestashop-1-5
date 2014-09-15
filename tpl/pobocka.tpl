{if $showtext}
    <div class="box">
        {l s='We will inform you as soon as your order is ready' mod='ulozenka'}<br />
    {/if}
    <a href='{$pobocka.link}' target='_blank'>{$pobocka.name}</a><br />

    {$pobocka.zip} {$pobocka.town}<br />
    {$pobocka.phone}<br />
    {$pobocka.street}<br />
    {$pobocka.provoz}<br />
    {$pobocka.gps.latitude} {$pobocka.gps.longitude}<br />

    <br />
    {$pobocka.map}

</div>

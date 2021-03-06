<ul class="list">
    <li class="subheader">
        <p class="normal">
            {$c->__('page.chats')}
        </p>
    </li>
</ul>
<ul id="chats_widget_list" class="list middle active divided spaced">
    {autoescape="off"}
        {$c->prepareChats(true)}
    {/autoescape}
</ul>

<div class="placeholder">
    <i class="material-icons">chat</i>
    <h1>{$c->__('chats.empty_title')}</h1>
    <h4>{$c->___('chats.empty', '<i class="material-icons">search</i>')}</h4>
</div>

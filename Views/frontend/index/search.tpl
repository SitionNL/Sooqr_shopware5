{extends file="parent:frontend/index/search.tpl"}

{block name='frontend_index_search_container'}
    <form class="main-search--form">
    {block name='frontend_index_search_field'}
        <input type="search" name="sSearch" id="search" class="main-search--field" autocomplete="off" autocapitalize="off" placeholder="{s name="IndexSearchFieldPlaceholder"}{/s}" maxlength="30"  />
    {/block}
    </form>

    {* Smarty ignores delimiters that are surrounded by whitespace *}
    <script type="text/javascript">

        {block name='frontend_index_search_sooqr_script'}

            var _wssq = _wssq || [];
            var SooqrAccountId = '{$sooqrAccountId}';
            var search = document.getElementById('search');

            _wssq.push(['_load', { 'suggest' : { 'account' : SooqrAccountId, 'version' : 4, fieldId : 'search' } } ]);

            // snippets configured in the backend
            {$jsSnippets}
            
        {/block}

        (function() {
            var ws = document.createElement('script'); ws.type = 'text/javascript'; ws.async = true;
            ws.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'static.sooqr.com/sooqr.js';
            var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ws, s);
        })();

    </script>

{/block}

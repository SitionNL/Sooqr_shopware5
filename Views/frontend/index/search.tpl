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
            var setResizeFunction = false;
            var SooqrAccountId = '{$SooqrAccountId}';

            _wssq.push(['_load', { 'suggest' : { 'account' : SooqrAccountId, 'version' : 4, fieldId : 'search' } } ]); // Plaats hier de account code en het id van het zoek veld

            // _wssq.push(['suggest._setPosition', 'element-relative', 'div.top-container']);
            _wssq.push(['suggest._setPosition', 'screen-middle', { top:0 } ]);
            // _wssq.push(['suggest._setLocale', 'nl_NL']);
            // _wssq.push(['suggest._excludePlaceholders', 'Ik ben op zoek naar...']); // Plaats hier je placeholder zodat deze Sooqr niet activeert in sommige browsers

            // _wssq.push(['suggest._bindEvent', 'open', function(event) {
            //     if(!setResizeFunction) {
            //     	$jQ( window ).resize(function() {
            //     		if( $jQ('.sooqrSearchContainer-' + SooqrAccountId).is(':visible') ) { 
            //     			websight.sooqr.instances['SQ-' + SooqrAccountId].positionContainer(null, null, true);
            //     		}
            //     	} );

            //     	setResizeFunction = true;
            //     }
            // }]);
        {/block}

        (function() {
            var ws = document.createElement('script'); ws.type = 'text/javascript'; ws.async = true;
            ws.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'static.sooqr.com/sooqr.js';
            var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ws, s);
        })();

    </script>

{/block}

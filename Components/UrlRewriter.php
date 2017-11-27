<?php

namespace Shopware\SitionSooqr\Components;

class UrlRewriter
{
    /**
     * Helper function to get the sRewriteTable class with auto completion.
     *
     * @return sRewriteTable
     */
    public function RewriteTable()
    {
        return Shopware()->Modules()->RewriteTable();
    }

    /**
     * Copy of
     * engine/Shopware/Plugins/Default/Core/RebuildIndex/Controllers/Seo.php - seoArticle
     * or
     * engine/Shopware/Core/sRewriteTable.php - sCreateRewriteTableArticles
     *
     * Get rewrite-url for articles
     * and write to db / return
     *
     * @param Shopware\Models\Shop\Shop $shop
     * @param array $articleIds
     */
    public function rewriteArticles($shop, $articleIds = [])
    {
        if( !count($articleIds) ) return;

        $this->RewriteTable()->baseSetup();

        $template = Shopware()->Template();
        $data = $template->createData();
        $data->assign('sConfig', Shopware()->Config());
        $data->assign('sRouter', $this->RewriteTable());
        $data->assign('sCategoryStart', $shop->getCategory()->getId());

        $sql = $this->RewriteTable()->getSeoArticleQuery();

        // only select for current articles
        $joinArticleIds = implode(',', $articleIds);
        $sql = str_replace('WHERE ', "WHERE a.id IN ({$joinArticleIds}) AND ", $sql);

        $shopFallbackId = ($shop->getFallback() instanceof \Shopware\Models\Shop\Shop) ? $shop->getFallback()->getId() : null;

        $articles = Shopware()->Db()->fetchAll($sql, [
            $shop->get('parentID'),
            $shop->getId(),
            $shopFallbackId,
            '1900-01-01',
        ]);

        $articles = $this->RewriteTable()->mapArticleTranslationObjectData($articles);

        $newPaths = [];

        foreach( $articles as $article )
        {
            $data->assign('sArticle', $article);

            $path = $template->fetch(
                'string:' . Shopware()->Config()->get('sRouterArticleTemplate'),
                $data
            );

            $path = $this->RewriteTable()->sCleanupPath($path);
            $orgPath = 'sViewport=detail&sArticle=' . $article['id'];

            $this->RewriteTable()->sInsertUrl($orgPath, $path);

            $newPaths[ $article['id'] ] = $path;
        }

        return $newPaths;
    }
}

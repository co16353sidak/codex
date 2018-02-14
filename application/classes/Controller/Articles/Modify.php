<?php defined('SYSPATH') or die('No direct script access.');

use \CodexEditor\CodexEditor;

class Controller_Articles_Modify extends Controller_Base_preDispatch
{
    /**
     * this method prevent no admin users visit /article/add, /article/<article_id>/edit
     */
    public function before()
    {
        parent::before();
        if (!$this->user->checkAccess(array(Model_User::ROLE_ADMIN))) {
            throw new HTTP_Exception_403();
        }
    }

    public function action_save()
    {
        $csrfToken = Arr::get($_POST, 'csrf');

        /*
         * редактирвоание происходит напрямую из роута вида: <controller>/<action>/<id>
         * так как срабатывает обычный роут, то при отправке формы передается переменная contest_id.
         * Форма отправляет POST запрос
         */
        if ($this->request->post()) {
            $article_id = Arr::get($_POST, 'article_id');
            $article = Model_Article::get($article_id, true);
        }

        /*
        * Редактирование через Алиас
        * Здесь сперва запрос получает Controller_Uri, которая будет передавать id сущности через query('id')
        */
        elseif ($article_id = $this->request->query('id') ?: $this->request->param('id')) {
            $article = Model_Article::get($article_id, true);
        } else {
            $article = new Model_Article();
        }

        $feed = new Model_Feed_Articles($article::FEED_PREFIX);

        /*
         * Articles Title.
         */
        if (!Security::check($csrfToken)) {
            goto theEnd;
        }

        $pageContent = Arr::get($_POST, 'article_text', '');       
        try {
            $editor = new CodexEditor($pageContent);
        } catch (Kohana_Exception $e) {
            throw new Kohana_Exception($e->getMessage());
        }

        $article->lang = Arr::get($_POST, 'lang');
        $article->title = Arr::get($_POST, 'title');
        $article->description = Arr::get($_POST, 'description');
        $article->text = $editor->getData();

        $article->is_published = Arr::get($_POST, 'is_published') ? 1 : 0;
        $article->marked       = Arr::get($_POST, 'marked') ? 1 : 0;
        $article->quiz_id      = Arr::get($_POST, 'quiz_id');
        $courses_ids           = Arr::get($_POST, 'courses_ids', 0);

        /**
         * Link only if this article exists
         */
        if ($article->id) {

            $linked_article_id = Arr::get($_POST, 'linked_article');

            /** Create links */
            if ($linked_article_id != 0) {

                $second_article = Model_Article::get($linked_article_id);

                /** Second article has no link */
                if (!$second_article->linked_article) {
                    
                    /** If this article was linked */
                    if ($article->linked_article) {
                        
                        /** Unlink the old one linked article */
                        $oldLinkedArticle = Model_Article::get($linked_article_id);
                        $oldLinkedArticle->linkWithArticle();
                    }

                    // link second article to first
                    $article->linkWithArticle($linked_article_id);

                    // link first article to second
                    $second_article->linkWithArticle($article->id);

                /** Second article was linked with other one */
                } elseif ($second_article->linked_article != $article->id) {

                    /** We can't link then show error */
                    $this->view['error'] = 'You can\'t link already linked article';
                    goto theEnd;
                }

                /** If second article was linked with this article then do nothing */

            /** Remove both links */    
            } elseif ($article->linked_article) {

                // remove "first <- second" link
                $article->linkWithArticle();

                // remove "second <- first" link
                $second_article = Model_Article::get($article->linked_article);
                $second_article->linkWithArticle();
            }

            /** If linked_article_id == 0 and $article->linked_article == 0 then do nothing*/
        }

        /**
         * @var string $item_below_key
         * Ключ элемента в фиде, над которым нужно поставить данную статью ('[article|course]:<id>')
         * */
        $item_below_key = Arr::get($_POST, 'item_below_key', 0);

        if (!$article->text) {
            $this->view['error'] = 'А где само тело статьи?';
            goto theEnd;
        }

        if (!$article->title) {
            $this->view['error'] = 'Не заполнен заголовок';
            goto theEnd;
        }

        if (!$article->description) {
            $this->view['error'] = 'Не заполнено описание. Это важное поле: опишите коротко, о чем пойдет речь в статье';
            goto theEnd;
        }

        $uri = Arr::get($_POST, 'uri');
        $alias = Model_Alias::generateUri($uri);

        if ($article_id) {
            $article->uri = Model_Alias::updateAlias($article->uri, $alias, Model_Uri::ARTICLE, $article_id);
            $article->dt_update = date('Y-m-d H:i:s');
            $article->update();
        } else {
            $article->user_id = $this->user->id;
            $insertedId = $article->insert();
            $article->uri = Model_Alias::addAlias($alias, Model_Uri::ARTICLE, $insertedId);
            $article->update();
        }

        if (!$courses_ids) {
            Model_Courses::deleteArticles($article->id);

            if ($article->is_published && !$article->is_removed) {
                $feed->add($article->id, $article->dt_create);

                //Ставим статью в переданное место в фиде, если это было указано
                if ($item_below_key) {
                    $feed->putAbove($article->id, $item_below_key);
                }
            } else {
                $feed->remove($article->id);
            }
        } else {
            $current_courses = Model_Courses::getCoursesByArticleId($article);

            if ($current_courses) {
                $courses_to_delete = array_diff($current_courses, $courses_ids);
                $courses_to_add = array_diff($courses_ids, $current_courses);

                Model_Courses::deleteArticles($article->id, $courses_to_delete);

                foreach ($courses_to_add as $course_id) {
                    Model_Courses::addArticle($article->id, $course_id);
                }
            } else {
                foreach ($courses_ids as $course_id) {
                    Model_Courses::addArticle($article->id, $course_id);
                }
            }

            $feed->remove($article->id);
        }

        $isRecent = Arr::get($_POST, 'is_recent') ? 1 : 0;
        $recentArticlesFeed = new Model_Feed_RecentArticles();
        if ($isRecent) {
            $recentArticlesFeed->add($article->id, true);
        } else {
            $recentArticlesFeed->remove($article->id, true);
        }

        // Если поле uri пустое, то редиректить на обычный роут /article/id
        $redirect = ($uri) ? $article->uri : '/article/' . $article->id;
        $this->redirect($redirect);

        theEnd:

        $this->view['article']          = $article;
        $this->view['linked_articles']  = Model_Article::getActiveArticles();
        $this->view['languages']        = ['ru', 'en'];
        $this->view['courses']          = Model_Courses::getActiveCoursesNames();
        $this->view['selected_courses'] = Model_Courses::getCoursesByArticleId($article);
        $this->view['topFeed']          = $feed->get(5);
        $this->view['quizzes']          = Model_Quiz::getTitles();

        $this->template->content = View::factory('templates/articles/create', $this->view);
    }


    public function action_delete()
    {
        $user_id = $this->user->id;
        $article_id = $this->request->param('article_id') ?: $this->request->query('id');

        if (!empty($article_id) && !empty($user_id)) {
            $article = Model_Article::get($article_id);
            $article->remove($user_id);

            $feed = new Model_Feed_Articles($article::FEED_PREFIX);
            $feed->remove($article->id);
        }

        $this->redirect('/admin/articles');
    }
}

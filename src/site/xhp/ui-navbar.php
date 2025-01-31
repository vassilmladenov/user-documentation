<?hh // strict
/*
 *  Copyright (c) 2004-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

use type HHVM\UserDocumentation\{NavDataNode, UIGlyphIcon};

use namespace HH\Lib\Dict;

class :ui:navbar extends :x:element {
  attribute
    dict<string, NavDataNode> data @required,
    vec<string> activePath @required,
    string extraNavListClass;

  protected function render(): XHPRoot {
    $roots = Dict\map($this->:data, $node ==> $this->renderLevel1Item($node));

    $nav_list_class = 'navList';
    $extra = $this->:extraNavListClass;
    if ($extra !== null) {
      $nav_list_class .= ' '.$extra;
    }

    $toggle_button =
      <div class="navToggleButton">
        <ui:glyph icon={UIGlyphIcon::LIST} />
      </div>;

    $list = (
      <ul class={$nav_list_class}>
        {$roots}
      </ul>
    );

    $container = (
      <div class="navOuterContainer navToggleOff">
        {$toggle_button}
        <div class="navInnerContainer">
          {$list}
        </div>
      </div>
    );

    $container->appendChild([
      $this->getToggleScript($toggle_button, $container),
      $this->getScrollToActiveScript($list),
    ]);

    return $container;
  }

  private function getToggleScript(:div $button, :div $container): :script {
    $button_id = json_encode($button->getID());
    $container_id = json_encode($container->getID());
    return (
      <script language="javascript">
        var toggleButton = document.getElementById({$button_id});
        toggleButton.addEventListener(
        'click',
        function() {"{"}
        var toggleContainer = document.getElementById({$container_id});
        toggleContainer.classList.toggle('navToggleOff');
        toggleContainer.classList.toggle('navToggleOn');
        {"}"}
        );
      </script>
    );
  }

  private function getScrollToActiveScript(:ul $list): :script {
    $path = $this->:activePath;
    if (!$path) {
      return <script />;
    }

    $id = implode('/', $path);

    return (
      <script language="javascript">
        var scrollToActive = function() {"{"}
        var navList = document.getElementById({json_encode($list->getID())});
        var activeNav = document.getElementById({json_encode($id)});
        navList.scrollTop = activeNav.offsetTop - 10;
        {"}"};
        scrollToActive();
        window.addEventListener(
        'transitioned',
        scrollToActive
        );
      </script>
    );
  }

  private function isActive(NavDataNode ...$nodes): bool {
    $idx = 0;
    $active = $this->:activePath;
    foreach ($nodes as $node) {
      if (!array_key_exists($idx, $active)) {
        return false;
      }
      if ($active[$idx] !== $node['name']) {
        return false;
      }
      ++$idx;
    }
    return true;
  }

  /* Caching: the uncached performance is completely fine for prod, but much too
   * slow when opening every possible page in the test suite.
   */
  private function renderLevel1Item(NavDataNode $node): XHPRoot {
    return $this->cachedRender(
      $node['name'].'//'.$node['urlPath'],
      $this->isActive($node),
      () ==> $this->renderLevel1ItemImpl($node),
    );
  }

  private function renderLevel1ItemImpl(NavDataNode $node): XHPRoot {
    $children = $this->renderChildren(
      'subList',
      $node,
      $child ==> $this->renderLevel2Item($node, $child),
    );

    $class = 'navGroup';
    if ($this->isActive($node)) {
      $class .= ' navGroupActive';
    }

    return
      <li class={$class}>
        <h4 id={$node['name']}>
          <a class="navItem" href={$node['urlPath']}>
            {$node['name']}
          </a>
        </h4>
        {$children}
      </li>;
  }

  /* Caching: the uncached performance is completely fine for prod, but much too
   * slow when opening every possible page in the test suite.
   */
  private function renderLevel2Item(
    NavDataNode $parent,
    NavDataNode $node,
  ): XHPRoot {
    return $this->cachedRender(
      $parent['name'].'//'.$node['name'].'//'.$node['urlPath'],
      $this->isActive($parent, $node),
      () ==> $this->renderLevel2ItemImpl($parent, $node),
    );
  }


  private function renderLevel2ItemImpl(
    NavDataNode $parent,
    NavDataNode $node,
  ): XHPRoot {
    $id = $parent['name'].'/'.$node['name'];

    $children = $this->renderChildren(
      'secondLevelList',
      $node,
      $child ==> $this->renderLevel3Item($parent, $node, $child),
    );

    $class = 'subListItem';
    if ($this->isActive($parent, $node)) {
      $class .= ' itemActive';
    }

    return (
      <li class={$class} id={$id}>
        <h5>
          <a class="navItem" href={$node['urlPath']}>
            {$node['name']}
          </a>
        </h5>
        {$children}
      </li>
    );
  }

  private function renderLevel3Item(
    NavDataNode $grandparent,
    NavDataNode $parent,
    NavDataNode $node,
  ): :li {
    $id = $grandparent['name'].'/'.$parent['name'].'/'.$node['name'];
    $class = 'secondLevelListItem';
    if ($this->isActive($grandparent, $parent)) {
      $class .= ' itemActive';
      if ($this->isActive($grandparent, $parent, $node)) {
        $class .= ' secondLevelItemActive';
      }
    }

    return (
      <li class={$class} id={$id}>
        <h6>
          <a class="navItem" href={$node['urlPath']}>{$node['name']}</a>
        </h6>
      </li>
    );
  }

  private function renderChildren(
    string $list_class,
    NavDataNode $parent,
    (function(NavDataNode): XHPChild) $render_func,
  ): ?XHPChild {
    if (!$parent['children']) {
      return null;
    }

    $root = <ul class={$list_class} />;
    foreach ($parent['children'] as $child) {
      $root->appendChild(
        $render_func(/* HH_IGNORE_ERROR[4110] mixed to shape */ $child),
      );
    }
    return $root;
  }

  private function cachedRender(
    string $cache_key,
    bool $is_active,
    (function(): XHPRoot) $callback,
  ): XHPRoot {
    if ($is_active) {
      return $callback();
    }

    $cache_key .= '!!!'.self::class.'!!!';
    $stored = APCCachedRenderable::get($cache_key);
    if ($stored) {
      return $stored;
    }

    $result = $callback();
    APCCachedRenderable::store($cache_key, $result);
    return $result;
  }
}

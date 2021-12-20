<?php
//this partial requires functions.php for the pagination_filter() helper
//and it required $page and $total_pages variables to be set prior
if (!isset($page)) {
    $page = 1;
}
if (!isset($total_pages)) {
    $total_pages = 1;
} ?>
<nav>
    <ul class="pagination">
        <li class="page-item <?php if (($page - 1) < 1) echo 'disabled'; ?>">
            <a class="page-link" href="?<?php se(persistQueryString($page - 1)); ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
            <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                <a class="page-link" href="?<?php se(persistQueryString($i)); ?>">
                    <?php se($i); ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php if (($page + 1) > $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="?<?php se(persistQueryString($page + 1)); ?>">Next</a>
        </li>
    </ul>
</nav>
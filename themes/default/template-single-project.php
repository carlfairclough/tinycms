<article>
    <h1><?php echo e(get_content('Title')); ?></h1>
    <?php if (get_content('Content') !== ''): ?>
        <p><?php echo nl2br(e(get_content('Content'))); ?></p>
    <?php endif; ?>
    <?php print_markdown('Markdown content'); ?>
</article>

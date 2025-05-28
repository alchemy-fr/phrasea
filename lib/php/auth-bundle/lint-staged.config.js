module.exports = {
    '*.php': () => 'composer cs || (composer install --no-interaction --no-scripts --no-progress && composer cs',
}

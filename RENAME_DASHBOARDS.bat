@echo off
echo Renaming dashboard files to shorter names...
echo.

cd dashboards

if exist admin-dashboard.php (
    ren admin-dashboard.php admin.php
    echo ✓ Renamed admin-dashboard.php to admin.php
)

if exist cashier-dashboard.php (
    ren cashier-dashboard.php cashier.php
    echo ✓ Renamed cashier-dashboard.php to cashier.php
)

if exist accountant-dashboard.php (
    ren accountant-dashboard.php accountant.php
    echo ✓ Renamed accountant-dashboard.php to accountant.php
)

if exist manager-dashboard.php (
    ren manager-dashboard.php manager.php
    echo ✓ Renamed manager-dashboard.php to manager.php
)

if exist waiter-dashboard.php (
    ren waiter-dashboard.php waiter.php
    echo ✓ Renamed waiter-dashboard.php to waiter.php
)

cd ..

echo.
echo ========================================
echo All dashboard files renamed successfully!
echo ========================================
echo.
echo New URLs:
echo - http://localhost/wapos/dashboards/admin.php
echo - http://localhost/wapos/dashboards/cashier.php
echo - http://localhost/wapos/dashboards/accountant.php
echo - http://localhost/wapos/dashboards/manager.php
echo - http://localhost/wapos/dashboards/waiter.php
echo.
pause

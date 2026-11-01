"""Synthetic, isolated browser verification. Credentials come from process env only."""
import json, os
from pathlib import Path
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait

ROOT = Path('/tmp/beta-admin-browser-audit')
SHOTS = ROOT / "browser-screenshots"
SHOTS.mkdir(parents=True, exist_ok=True)
BASE = "http://127.0.0.1:8898"
pages = [
    ("dashboard", "/staff/dashboard"), ("announcements", "/staff/announcements/all"),
    ("announcement-review", "/staff/announcements"),
    ("groups", "/staff/announcement-groups"), ("clients", "/staff/clients"),
    ("employer-review", "/staff/employers"),
    ("candidates", "/staff/candidates"), ("users", "/staff/users"),
    ("roles", "/staff/roles"), ("introductions", "/staff/introductions"),
    ("info", "/staff/information-pages"), ("portfolio", "/staff/portfolio"),
    ("settings", "/staff/settings"), ("references", "/staff/references"),
    ("audit", "/staff/audit"), ("ads", "/staff/ads"),
    ("testimonials", "/staff/testimonials"),
]
options = Options()
options.binary_location = "/usr/bin/chromium"
for argument in ("--headless", "--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage"):
    options.add_argument(argument)
options.set_capability("goog:loggingPrefs", {"browser": "ALL"})
driver = webdriver.Chrome(service=Service('/usr/bin/chromedriver'), options=options)
wait = WebDriverWait(driver, 15)
results = []
try:
    driver.set_window_size(1440, 1000)
    driver.get(BASE + "/login")
    driver.find_element(By.NAME, "email").send_keys("audit-admin@example.test")
    driver.find_element(By.NAME, "password").send_keys(os.environ["BETA_BROWSER_PASSWORD"])
    print(driver.execute_script("return [...document.querySelectorAll('input')].map(i => [i.name, i.type, i.value])"))
    driver.find_element(By.CSS_SELECTOR, "[data-test='login-button']").click()
    try:
        wait.until(lambda d: d.current_url.endswith("/staff/dashboard"))
    except Exception:
        print(driver.current_url)
        print(driver.find_element(By.TAG_NAME, "body").text[:2000])
        driver.save_screenshot(str(ROOT / "login-failure.png"))
        raise
    for locale in ("fr", "ar"):
        driver.add_cookie({"name": "locale", "value": locale, "path": "/"})
        for width, height, device in ((1440, 1000, "desktop"), (390, 844, "mobile")):
            driver.set_window_size(width, height)
            for name, path in pages:
                driver.get(BASE + path)
                wait.until(lambda d: d.execute_script("return document.readyState") == "complete")
                errors = [entry["message"] for entry in driver.get_log("browser") if entry["level"] == "SEVERE"]
                result = {"name": name, "locale": locale, "device": device,
                    "url": driver.current_url.replace(BASE, ""),
                    "lang": driver.execute_script("return document.documentElement.lang"),
                    "dir": driver.execute_script("return document.documentElement.dir"),
                    "overflow": driver.execute_script("return document.documentElement.scrollWidth > innerWidth + 2"),
                    "errors": errors}
                results.append(result)
                if name in {"announcements", "clients", "candidates", "ads", "info"}:
                    driver.save_screenshot(str(SHOTS / f"{name}-{locale}-{device}.png"))
                if result["url"] != path or errors or result["overflow"]:
                    raise RuntimeError(json.dumps(result, ensure_ascii=False))
finally:
    driver.quit()
(ROOT / "browser-verification.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
print(json.dumps({"pages": len(results), "errors": 0}))

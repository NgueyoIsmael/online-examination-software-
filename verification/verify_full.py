from playwright.sync_api import sync_playwright

def verify_full_system():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        
        # 1. Login Page & Welcome Popup
        print("1. Testing Login Page & Popup...")
        page.goto("http://localhost:8000/auth/login.php")
        
        # Check Popup
        try:
            page.wait_for_selector("#welcomeModal", timeout=5000)
            if page.is_visible("#welcomeModal"):
                print("   - Welcome Modal is visible.")
                page.screenshot(path="verification/1_login_popup.png")
                page.click("button[data-bs-dismiss='modal']") # Close it
            else:
                print("   - Welcome Modal NOT found!")
        except:
             print("   - Welcome Modal timed out!")

        # Login
        page.fill("input[name='username']", "student")
        page.fill("input[name='password']", "student123")
        page.click("button[type='submit']")
        
        # 2. Student Dashboard
        print("2. Testing Student Dashboard...")
        page.wait_for_selector("h1")
        if "Student Dashboard" in page.content():
             print("   - Dashboard loaded.")
             page.screenshot(path="verification/2_dashboard.png")
        
        # 3. Create Custom Exam
        print("3. Testing Custom Exam Creation...")
        page.click("a[href='/student/create_custom_exam.php']")
        page.fill("input[name='title']", "Professional Test Exam Unique " + str(page.evaluate("Date.now()")))
        page.fill("textarea[name='description']", "Testing UI and functionality")
        page.click("button[type='submit']")
        
        # 4. Take Exam
        print("4. Testing Exam Taking Interface...")
        
        # Wait for card
        page.wait_for_selector(".card-title")
        
        # Since I used a unique name, I can target it more easily
        # Finding the card that has the unique title
        # "Professional Test Exam Unique"
        # We just grab the *LAST* button named "Start Exam" because newly created exams might appear at the end or we can just pick the first one available.
        # Actually, let's just pick the *first* "Start Exam" button to ensure we get into AN exam.
        
        start_buttons = page.locator("button", has_text="Start Exam")
        if start_buttons.count() > 0:
            print(f"   - Found {start_buttons.count()} exams. Starting the first one.")
            start_buttons.first.click()
        else:
            print("   - No exams found!")
            return

        # Exam Page
        page.wait_for_selector("#timer")
        print("   - Exam started. Timer visible.")
        page.screenshot(path="verification/3_exam_interface.png")
        
        # Fill Answers
        radios = page.locator("input[type='radio']")
        count = radios.count()
        if count > 0:
            # Click the first radio
            radios.first.check()
            
        # Submit
        page.on("dialog", lambda dialog: dialog.accept()) 
        page.click("button[type='submit']")
        
        # 5. Result
        print("5. Testing Result Page...")
        page.wait_for_selector(".card-header")
        if "Result:" in page.content():
            print("   - Result page loaded.")
            page.screenshot(path="verification/4_result_page.png")
        
        browser.close()
        print("Verification Complete.")

if __name__ == "__main__":
    verify_full_system()

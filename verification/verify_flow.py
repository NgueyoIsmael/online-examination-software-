from playwright.sync_api import sync_playwright

def verify_student_flow():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        
        # 1. Login as Student
        page.goto("http://localhost:8000/auth/login.php")
        page.fill("input[name='username']", "student")
        page.fill("input[name='password']", "student123")
        page.click("button[type='submit']")
        
        # Verify Dashboard
        page.wait_for_selector("h1")
        assert "Student Dashboard" in page.content()
        page.screenshot(path="verification/1_student_dashboard.png")
        print("Student Dashboard verified.")

        # 2. Create Custom Exam
        page.click("a[href='/student/create_custom_exam.php']")
        page.wait_for_selector("h1")
        assert "Create Custom Exam" in page.content()
        
        page.fill("input[name='title']", "My Playwright Exam")
        page.fill("textarea[name='description']", "Testing with Playwright")
        page.click("button[type='submit']") # This creates and redirects to dashboard (or take exam? Code says dashboard)

        # Verify new exam is in list and start it
        page.wait_for_selector("h5") # Titles
        # Find the start button for the new exam. 
        # Since it's a list, and we just added it, it should be there.
        # But wait, available exams query logic might need checking. 
        # "Available Exams" shows exams with status='open'. Custom exams are created with status='open'.
        
        # Taking a screenshot of the dashboard with the new exam
        page.screenshot(path="verification/2_dashboard_with_custom_exam.png")
        
        # 3. Start Exam (Find the last one or by title)
        # We need to find the form inside the card with title "My Playwright Exam"
        # Using xpath to find the card body containing the title, then the button
        card = page.locator(".card", has_text="My Playwright Exam")
        card.locator("button", has_text="Start Exam").click()
        
        # 4. Take Exam Page
        page.wait_for_selector("#timer")
        assert "My Playwright Exam" in page.content()
        page.screenshot(path="verification/3_take_exam.png")
        print("Exam page verified.")
        
        # 5. Submit Exam
        # Just submit empty or partially filled
        page.on("dialog", lambda dialog: dialog.accept()) # Handle confirm dialog
        page.click("button[type='submit']")
        
        # 6. Result Page
        page.wait_for_selector(".card-header")
        assert "Result:" in page.content()
        page.screenshot(path="verification/4_result.png")
        print("Result page verified.")

        browser.close()

if __name__ == "__main__":
    verify_student_flow()

# ----------------------------------------------------------------------
# Stage 1: Build Environment
# Used to install dependencies, compile assets, or run tests.
# This stage uses a full base image for development/building.
# ----------------------------------------------------------------------
FROM python:3.11-slim AS builder

# Set the working directory
WORKDIR /app

# Copy requirement files and install dependencies
# This step is cached efficiently if requirements don't change
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application code
COPY . .

# Optional: Run tests or any necessary build scripts here
# RUN python setup.py build
# ----------------------------------------------------------------------

# ----------------------------------------------------------------------
# Stage 2: Final Production Environment
# This stage uses a minimal base image (often the same slim version)
# and only copies the necessary runtime files from the 'builder' stage.
# ----------------------------------------------------------------------
FROM python:3.11-slim

# Set the working directory
WORKDIR /usr/src/app

# Copy only the installed packages and application code from the builder stage
# This keeps the final image lean by excluding build tools and temporary files.
COPY --from=builder /usr/local/lib/python3.11/site-packages /usr/local/lib/python3.11/site-packages
COPY --from=builder /usr/local/bin /usr/local/bin
COPY --from=builder /app /usr/src/app

# Define the port your application listens on
EXPOSE 8080

# Define environment variables (if needed)
# ENV ENV_VARIABLE=value

# Command to run the application when the container starts
CMD ["python", "YOUR_APP_NAME.py"]

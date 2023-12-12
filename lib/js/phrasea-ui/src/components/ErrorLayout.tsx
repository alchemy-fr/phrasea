import React, {PropsWithChildren, ReactNode, useRef} from "react";
import styled, {keyframes} from "styled-components";

const asd = keyframes`
    0% {
        top: -20%;
    }
    100% {
        top: 100%;
    }
`

const asdd = keyframes`
    0% {
        text-shadow: 0 0 5px rgba(0, 0, 0, .9);
    }
    33% {
        text-shadow: 0 0 2px rgba(0, 0, 0, .8);
    }
    66% {
        text-shadow: 0 0 3px rgba(0, 0, 0, .6);
    }
    100% {
        text-shadow: 0 0 4px rgba(0, 0, 0, .9);
    }
`

const SubFrame = styled.div`
position: absolute;
left: 0;
top: -20%;
width: 100%;
height: 20%;
background-color: rgba(0, 0, 0, .12);
box-shadow: 0 0 10px rgba(0, 0, 0, .3);
animation: ${asd} 12s linear infinite;
`

const Content = styled.div`
    z-index: 3;
    position: absolute;
    left: 50%;
    top: 50%;
    font: bold 30px/30px Arial, sans-serif;
    transform: translateY(-50%) translateX(-50%);
    width: 100%;
    color: transparent;
    text-align: center;
    text-shadow: 0 0 30px rgba(0, 0, 0, .5);
    animation: ${asdd} 2s linear infinite;
`

type Props = PropsWithChildren<{
    title: ReactNode;
    description: ReactNode;
}>;

export default function ErrorLayout({
    title,
    description,
    children,
}: Props) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    React.useEffect(() => {
        if (!canvasRef.current) {
            return;
        }

        const canvas = canvasRef.current!;
        const ctx = canvas.getContext('2d')!;
        const width = 700;
        const height = 500;

        canvas.width = width;
        canvas.height = height;
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, width, height);
        ctx.fill();
        const imgData = ctx.getImageData(0, 0, width, height);
        const pix = imgData.data;

        function flickering() {
            for (let i = 0; i < pix.length; i += 4) {
                const color = (Math.random() * 255) + 50;
                pix[i] = color;
                pix[i + 1] = color;
                pix[i + 2] = color;
            }
            ctx.putImageData(imgData, 0, 0);
        }

        const flickerInterval = setInterval(flickering, 60);

        return () => {
            clearInterval(flickerInterval);
        }
    }, [canvasRef.current]);

    return <div>
        <canvas
            ref={canvasRef}
            style={{
                zIndex: 1,
                position: 'absolute',
                left: 0,
                top: 0,
                opacity: .5,
                width: '100%',
                height: '100%',
            }}
        ></canvas>

        <div style={{
            zIndex: 3,
            position: 'absolute',
            left: 0,
            top: 0,
            width: '100%',
            height: '100%',
            background: 'radial-gradient(ellipse at center, rgba(0, 0, 0, 0) 0%, rgba(0, 0, 0, 0) 19%, rgba(0, 0, 0, 0.9) 100%)',
            filter: "progid:DXImageTransform.Microsoft.gradient(startColorstr = '#00000000', endColorstr = '#e6000000', GradientType = 1)"
        }}>
            <SubFrame/>
            <SubFrame style={{
                animationDelay: '4s',
            }} />
            <SubFrame style={{
                animationDelay: '8s',
            }} />
        </div>

        <div style={{
            maxWidth: 600,
            margin: '10px auto',
        }}>
            <Content>
                {title && <h1
                    style={{
                        fontSize: 50,
                        color: 'transparent'
                    }}
                >{title}</h1>}
                {description && <p>{description}</p>}
                {children}

                <div style={{
                    marginTop: 80,
                }}>
                    <a
                        style={{
                            border: '3px solid #000',
                            padding: '8px 7px',
                            color: '#000',
                            borderRadius: 5

                        }}
                        href="/"
                    >
                        Back to home
                    </a>
                </div>
            </Content>
        </div>
    </div>
}
